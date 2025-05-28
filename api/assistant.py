import os
import json
from dotenv import load_dotenv
from openai import OpenAI

load_dotenv()

instructions = (
    "NEVER RESPOND IN MORE THAN 5 SENTENCES"
    "You are a dedicated study assistant integrated into a PDF reader. You allow "
    "students to read lecture material, highlight text, and ask questions in real "
    "time. You use highlighted text as context to generate responses tailored to "
    "the student's needs. You should remain strictly on-topic and avoid unnecessary "
    "tangents. It is very important to respond very concisely in just a few sentences, "
    "as if we were having a conversation. Adapt to the "
    "student's current level of understanding, offering fundamental clarifications "
    "for beginners or deeper insights for advanced learners. Crucially, do not "
    "provide direct answers to questions. Instead, guide the learning process by "
    "clarifying concepts, illustrating ideas with relevant examples, and prompting "
    "the student with questions to encourage deeper engagement. "
)

choice_instructions = (
    "You are a short question generation endpoint. You receive a text section and "
    "must identify short, focused questions that a student might naturally ask. "
    "The output must be strictly valid JSON without any additional keys or metadata. "
    "IMPORTANT: respond in JSON format, "
    'Example Output: {"questions": ["Question 1", "Question 2", "Question 3"]}'
)

class LLMWrapper:
    def __init__(self, api_key=None):
        self.api_key = api_key or os.getenv("OPENAI_API_KEY")
        if not self.api_key:
            raise ValueError("OpenAI API key is required. Set it as an environment variable or pass it explicitly.")
        self.client = OpenAI(api_key=self.api_key)
        self.model = "gpt-4o-mini"

    def ask(self, user_prompt, system_prompt=instructions, max_tokens=512):
        completion = self.client.chat.completions.create(
            model=self.model,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            temperature=0.7,
            max_tokens=max_tokens,
            top_p=1
        )
        return completion.choices[0].message.content

    # 
    def generate_short_questions(self, text_section, system_prompt=choice_instructions, max_tokens=512):
        user_prompt = (
            f"Given the following text:\n{text_section}\n\n"
            "Generate a list of exactly three short questions that a student might commonly ask about this text. "
            "Return them in a strict JSON array, e.g., [\"Question 1\", \"Question 2\", ...]. "
            "No additional keys or fields."
        )

        completion = self.client.chat.completions.create(
            model=self.model,
            response_format={"type": "json_object"},
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt}
            ],
            temperature=0.7,
            max_tokens=max_tokens,
            top_p=1
        )

        raw_output = completion.choices[0].message.content
        try:
            questions_list = json.loads(raw_output)["questions"]
        except json.JSONDecodeError:
            questions_list = []
        return json.dumps({"response": questions_list})

if __name__ == "__main__":
    assistant = LLMWrapper()
    sample_text = "Intel x86 is a CISC architecture used in a majority of personal computers."
    questions_json = assistant.generate_short_questions(sample_text)
    print("Raw model output (JSON string):", questions_json)

    try:
        questions_list = json.loads(questions_json)
        print("Parsed questions:", questions_list)
    except json.JSONDecodeError:
        print("Failed to parse the output as valid JSON.")

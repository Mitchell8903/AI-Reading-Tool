import os
import json
from datetime import datetime
from dotenv import load_dotenv

from flask import Flask, request, jsonify

import ocr
from assistant import LLMWrapper

# Load environment variables from .env file
load_dotenv()

app = Flask(__name__)

assistant = LLMWrapper()

# Log file path
LOG_FILE = "assistant_requests_log.json"

# Ensure log file exists
if not os.path.exists(LOG_FILE):
    with open(LOG_FILE, "w") as f:
        json.dump([], f)


@app.route("/parse-pdf", methods=["POST"])
def parse_pdf():
    # Check if a file is provided
    if "file" not in request.files:
        return jsonify({"error": "No file provided"}), 400

    file = request.files["file"]

    # Check if the file is a PDF
    if file.filename == "" or not file.filename.lower().endswith(".pdf"):
        return jsonify({"error": "Invalid file type, only PDFs are allowed"}), 400

    try:
        file.save("temp.pdf")
        markdown_content = ocr.get_markdown_from_pdf("temp.pdf")
        return jsonify({"markdown": markdown_content})

    except Exception as e:
        print(f"Processing failed: {str(e)}")
        return jsonify({"error": f"Processing failed: {str(e)}"}), 500


@app.route('/assistant', methods=['POST'])
def ask_assistant():
    data = request.get_json()
    if not data or 'prompt' not in data or 'response_type' not in data:
        return jsonify({'error': 'Both prompt and response_type are required'}), 400

    prompt = data['prompt']
    response_type = data['response_type']
    conversation_history = data.get('conversation_history', '')

    if response_type not in ["clarify", "example", "test", "other"]:
        return jsonify({'error': 'Invalid response type.'}), 400

    # Modify prompt based on flag
    if response_type == 'clarify':
        prompt = f"Clarify the following briefly, as if we were having a conversation: {prompt}"
    elif response_type == 'example':
        prompt = f"Provide a brief example for the following briefly, as if we were having a conversation: {prompt}"
    elif response_type == 'test':
        prompt = f"Ask a brief related question to test understanding of the following, as if we were having a conversation: {prompt}"
    elif response_type == "other" and len(prompt) == 0:
        prompt = "No text is selected."
    else:
        prompt = f"(This question was freely entered, don't give direct answers and respond briefly, as if we were having a conversation) Question: {prompt}"

    # Insert previous conversation
    full_conversation = conversation_history + "\n\n" + prompt

    # Call assistant to get response
    response = assistant.ask(full_conversation)

    # Log the request and response
    log_entry = {
        "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "response_type": response_type,
        "prompt": prompt,
        "response": response
    }

    with open(LOG_FILE, "r+") as f:
        logs = json.load(f)
        logs.append(log_entry)
        f.seek(0)
        json.dump(logs, f, indent=4)

    return jsonify({'response': response})


@app.route("/suggest-options", methods=["POST"])
def generate_short_questions():
    data = request.get_json()
    if not data or 'selected_text' not in data:
        return jsonify({'error': 'A "selected_text" field is required'}), 400
    print(data)

    text_section = data['selected_text']

    # Generate questions in JSON string format
    questions_json_str = assistant.generate_short_questions(text_section)

    # Attempt to parse them into a Python list to ensure valid JSON
    try:
        questions_list = json.loads(questions_json_str)
    except json.JSONDecodeError:
        return jsonify({
            'error': "The model did not return valid JSON",
            'raw_output': questions_json_str
        }), 500

    # Return the parsed list directly as JSON
    print(questions_list)
    return jsonify(questions_list)


if __name__ == "__main__":
    port = int(os.getenv('FLASK_PORT', 5000))  # Default to 5000 if not set
    app.run(debug=True, host="0.0.0.0", port=port)

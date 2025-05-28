import os
import json
import time
from pathlib import Path
import logging
import traceback

from mistralai import Mistral, DocumentURLChunk
from mistralai.models import OCRResponse
from dotenv import load_dotenv
from tqdm import tqdm

load_dotenv()

api_key = os.getenv("MISTRAL_API_KEY")
vision_model = "pixtral-12b-2409"
ocr_model = "mistral-ocr-latest"

client = Mistral(api_key=api_key)

# Configure logging
logger = logging.getLogger(__name__)

# Generates a description for a given base64 image
def describe_image(base64_image: str) -> str:
    messages = [
        {
            "role": "user",
            "content": [
                {"type": "text", "text": "Write brief alternate text for this image to be accessible for all users. Only include one or two full sentences, no point form."},
                {"type": "image_url", "image_url": base64_image}
            ]
        }
    ]
    response = client.chat.complete(model=vision_model, messages=messages)
    return response.choices[0].message.content


# Inserts base64 images with descriptions for alt text
def replace_images_in_markdown(markdown_str: str, images_dict: dict) -> str:
    for img_name, base64_str in images_dict.items():
        description = describe_image(base64_str)
        markdown_str = markdown_str.replace(
            f"![{img_name}]({img_name})",
            f"![{description}]({base64_str})"
        )
        time.sleep(5)  # Keep requests under 1 request per second for free plan
    return markdown_str


# Combines pages from the OCRResponse object into a single string
def get_combined_markdown(ocr_response: OCRResponse) -> str:
    markdowns: list[str] = []
    for page in tqdm(ocr_response.pages, desc="Processing OCR pages"):
        image_data = {img.id: img.image_base64 for img in page.images}
        markdowns.append(replace_images_in_markdown(page.markdown, image_data))

    return "\n\n".join(markdowns)


# Takes a pdf file and returns markdown as a string
def get_markdown_from_pdf(pdf_name):
    try:
        pdf_file = Path(pdf_name)
        if not pdf_file.is_file():
            raise FileNotFoundError(f"File {pdf_name} not found.")
        
        logger.info(f"Processing PDF file: {pdf_name}")
        
        try:
            uploaded_file = client.files.upload(
                file={"file_name": pdf_file.stem, "content": pdf_file.read_bytes()},
                purpose="ocr",
            )
            logger.info(f"Successfully uploaded file to OCR service: {uploaded_file.id}")
        except Exception as e:
            logger.error(f"Failed to upload file to OCR service: {str(e)}")
            raise Exception(f"Failed to upload file to OCR service: {str(e)}")

        try:
            signed_url = client.files.get_signed_url(file_id=uploaded_file.id, expiry=1)
            logger.info("Successfully generated signed URL")
        except Exception as e:
            logger.error(f"Failed to generate signed URL: {str(e)}")
            raise Exception(f"Failed to generate signed URL: {str(e)}")

        try:
            pdf_response = client.ocr.process(
                document=DocumentURLChunk(document_url=signed_url.url),
                model=ocr_model,
                include_image_base64=True
            )
            logger.info("Successfully processed document with OCR")
        except Exception as e:
            logger.error(f"Failed to process document with OCR: {str(e)}")
            raise Exception(f"Failed to process document with OCR: {str(e)}")

        try:
            markdown = get_combined_markdown(pdf_response)
            logger.info("Successfully converted OCR response to markdown")
            return markdown
        except Exception as e:
            logger.error(f"Failed to convert OCR response to markdown: {str(e)}")
            raise Exception(f"Failed to convert OCR response to markdown: {str(e)}")

    except Exception as e:
        logger.error(f"Error in get_markdown_from_pdf: {str(e)}\n{traceback.format_exc()}")
        raise


if __name__ == "__main__":
    markdown_content = get_markdown_from_pdf("instruction_sets.pdf")
    print(markdown_content)

from pdfminer.high_level import extract_text

text = extract_text("sathsewa.pdf")
with open("pdf_output_miner.txt", "w", encoding="utf-8") as f:
    f.write(text)

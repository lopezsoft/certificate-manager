import pypdf
r = pypdf.PdfReader('Uso del API para perfiles PKCS#10 (V2).pdf')
print('PAGES', len(r.pages))
for i, p in enumerate(r.pages):
    print(f'\n===== PAGE {i+1} =====')
    print(p.extract_text())


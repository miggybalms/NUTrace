import zipfile, re, sys

p = sys.argv[1]
z = zipfile.ZipFile(p)
print("PARTS:")
for n in z.namelist():
    print("  ", n)
doc = z.read("word/document.xml").decode("utf-8")
print("LEN", len(doc))


def text_of(xml):
    xml = re.sub(r"<w:tab[^>]*/>", "\t", xml)
    xml = re.sub(r"<w:br[^>]*/>", "\n", xml)
    parts = re.findall(r"<w:t(?: [^>]*)?>(.*?)</w:t>", xml, re.S)
    return "".join(parts)


def spans(s, tag):
    out = []
    depth = 0
    start = None
    for m in re.finditer(r"<(/?)%s(?:\s[^>]*?)?(/?)>" % tag, s):
        if m.group(1) == "" and m.group(2) != "/":
            if depth == 0:
                start = m.start()
            depth += 1
        elif m.group(1) == "/":
            depth -= 1
            if depth == 0:
                out.append((start, m.end()))
    return out


tb = spans(doc, "w:tbl")
print("TABLES:", len(tb))
for i, (a, b) in enumerate(tb):
    print("=" * 70)
    print("TABLE", i)
    rows = spans(doc[a:b], "w:tr")
    for ri, (c, d) in enumerate(rows):
        cells = spans(doc[a + c:a + d], "w:tc")
        vals = []
        for (e, f) in cells:
            t = text_of(doc[a + c + e:a + c + f])
            vals.append(t.replace("\n", " / "))
        print(" r%d: %s" % (ri, " || ".join(vals)))

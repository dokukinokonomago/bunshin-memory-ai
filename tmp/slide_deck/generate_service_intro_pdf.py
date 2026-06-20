from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import re
from typing import Iterable

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageFont
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas


ROOT = Path("/Users/dinho/Desktop/ホーム/ITシステム制作/2026/分身AI")
WORK = ROOT / "tmp/slide_deck"
ASSETS = WORK / "assets"
SLIDES = WORK / "slides"
OUTPUT = ROOT / "output/pdf"

WIDTH = 1920
HEIGHT = 1080

FONT_SANS = "/System/Library/Fonts/ヒラギノ丸ゴ ProN W4.ttc"
FONT_SANS_BOLD = "/System/Library/Fonts/ヒラギノ角ゴシック W7.ttc"
FONT_SERIF = "/System/Library/Fonts/ヒラギノ明朝 ProN.ttc"
FONT_LABEL = "/System/Library/Fonts/Avenir Next.ttc"

PALETTE = {
    "ink": "#1f1a16",
    "warm_white": "#f8f2ea",
    "cream": "#f4ede3",
    "sand": "#d8c7b4",
    "taupe": "#7d6b5d",
    "gold": "#f3d9a6",
    "blush": "#efcfcb",
    "deep_blue": "#081223",
    "mist_blue": "#b7c9dc",
}


@dataclass
class SlideSpec:
    filename: str
    background: str
    title: str
    subtitle: str = ""
    body: tuple[str, ...] = ()
    accent: str = PALETTE["gold"]
    dark: bool = False
    screenshot: str | None = None
    screenshot_box: tuple[int, int, int, int] | None = None
    layout: str = "left"


SLIDES_SPEC = [
    SlideSpec(
        filename="01_cover.png",
        background="ig_0da2a47a66ae47e2016a27df156d188191b031fa512a654816.png",
        title="[[メモリル]]",
        subtitle="記憶を残す。未来の分身AIを育てる。",
        body=(
            "対面ご紹介資料",
            "感情・年代・カテゴリで、自分の人生をあとから探索できる記憶基盤",
        ),
        accent=PALETTE["gold"],
        dark=False,
        layout="left",
    ),
    SlideSpec(
        filename="02_problem.png",
        background="ig_0da2a47a66ae47e2016a27df9fff4c8191bf2ee85956b6a9b2.png",
        title="人は思い出を\n[[残せていない]]",
        body=(
            "日記は続かない",
            "写真だけでは感情が抜ける",
            "会話ログは、人生の文脈として残らない",
        ),
        accent=PALETTE["blush"],
        dark=False,
        layout="left-panel",
    ),
    SlideSpec(
        filename="03_solution.png",
        background="ig_0da2a47a66ae47e2016a27e0818098819191669853edb34cd8.png",
        title="会話や出来事を、\nあとで使える[[記憶]]に変える",
        subtitle="話す / 残す -> AIが整理 -> 探せる資産になる",
        body=(
            "年代",
            "感情",
            "カテゴリ",
            "タグ",
            "秘匿設定",
        ),
        accent=PALETTE["gold"],
        dark=False,
        layout="right-flow",
    ),
    SlideSpec(
        filename="04_experience.png",
        background="ig_0da2a47a66ae47e2016a27e0ed5a048191b3b639f618917809.png",
        title="記憶は、一覧でなく\n“[[宇宙]]”として探索できる",
        subtitle="大カテゴリから1つの記憶まで、ズームしてたどれる",
        body=(
            "カテゴリごとの星団",
            "感情ごとの色",
            "人生フェーズごとの見返し",
        ),
        accent=PALETTE["mist_blue"],
        dark=True,
        layout="left-dark",
    ),
    SlideSpec(
        filename="05_privacy.png",
        background="ig_0da2a47a66ae47e2016a27e16a1a58819181a490ec67c8f826.png",
        title="見せたくない記憶は、\n最初から[[別管理]]",
        subtitle="通常記憶と secret 記憶を混在させない設計",
        body=(
            "明示的に開いた時だけ表示",
            "専用アンロックで保護",
            "静かで慎重なUX",
        ),
        accent=PALETTE["gold"],
        dark=False,
        layout="right-panel",
    ),
    SlideSpec(
        filename="06_value.png",
        background="ig_0da2a47a66ae47e2016a27e22220948191ae1f9b4060163606.png",
        title="これは記録アプリではなく、\n[[人生データの基盤]]",
        subtitle="今は記憶基盤。将来は分身AIの精度を支える一次データへ。",
        body=(
            "振り返り支援",
            "自己理解",
            "分身AIの土台",
        ),
        accent=PALETTE["gold"],
        dark=False,
        layout="bottom-cards",
    ),
    SlideSpec(
        filename="07_close.png",
        background="ig_0da2a47a66ae47e2016a27df156d188191b031fa512a654816.png",
        title="あなたの記憶を、\n未来に残せる[[資産]]へ",
        subtitle="メモリル / 分身AI",
        body=(
            "記録 -> 整理 -> 探索 -> 分身AIへ",
        ),
        accent=PALETTE["gold"],
        dark=False,
        layout="center-close",
    ),
]


def font(path: str, size: int) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(path, size)


def hex_rgba(value: str, alpha: int = 255) -> tuple[int, int, int, int]:
    value = value.lstrip("#")
    return tuple(int(value[i : i + 2], 16) for i in (0, 2, 4)) + (alpha,)


def fit_cover(path: Path, size: tuple[int, int]) -> Image.Image:
    source = Image.open(path).convert("RGB")
    sw, sh = source.size
    tw, th = size
    scale = max(tw / sw, th / sh)
    resized = source.resize((int(sw * scale), int(sh * scale)), Image.Resampling.LANCZOS)
    left = (resized.width - tw) // 2
    top = (resized.height - th) // 2
    return resized.crop((left, top, left + tw, top + th))


def add_overlay(base: Image.Image, dark: bool, layout: str) -> Image.Image:
    overlay = Image.new("RGBA", base.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)

    if dark:
        draw.rectangle((0, 0, WIDTH, HEIGHT), fill=(4, 11, 24, 85))
        draw.rounded_rectangle((70, 80, 980, 960), radius=42, fill=(3, 10, 24, 175))
    elif layout in {"left", "left-panel"}:
        draw.rectangle((0, 0, 980, HEIGHT), fill=(247, 240, 231, 210))
        draw.ellipse((-240, -140, 560, 520), fill=(255, 255, 255, 85))
    elif layout == "right-panel":
        draw.rounded_rectangle((920, 80, 1820, 980), radius=42, fill=(248, 242, 234, 192))
        draw.rectangle((0, 0, WIDTH, HEIGHT), fill=(255, 248, 241, 24))
    elif layout == "right-flow":
        draw.rectangle((0, 0, 1040, HEIGHT), fill=(250, 245, 238, 205))
    elif layout == "bottom-cards":
        draw.rectangle((0, 0, WIDTH, HEIGHT), fill=(255, 250, 244, 42))
        draw.rounded_rectangle((90, 90, 1100, 540), radius=48, fill=(249, 241, 232, 200))
    elif layout == "center-close":
        draw.rectangle((0, 0, WIDTH, HEIGHT), fill=(255, 247, 240, 68))
        draw.rounded_rectangle((290, 210, 1630, 860), radius=52, fill=(248, 242, 233, 158))

    softened = overlay.filter(ImageFilter.GaussianBlur(6))
    return Image.alpha_composite(base.convert("RGBA"), softened)


def draw_text_block(draw: ImageDraw.ImageDraw, x: int, y: int, lines: Iterable[str], text_font, fill, line_gap: int) -> int:
    current_y = y
    for line in lines:
        draw.text((x, current_y), line, font=text_font, fill=fill)
        bbox = draw.textbbox((x, current_y), line, font=text_font)
        current_y = bbox[3] + line_gap
    return current_y


def draw_text_with_shadow(
    draw: ImageDraw.ImageDraw,
    xy: tuple[int, int],
    text: str,
    text_font,
    fill,
    shadow_fill,
    shadow_offset: int = 2,
) -> None:
    x, y = xy
    for dx, dy in ((shadow_offset, shadow_offset), (shadow_offset, 0), (0, shadow_offset)):
        draw.text((x + dx, y + dy), text, font=text_font, fill=shadow_fill)
    draw.text((x, y), text, font=text_font, fill=fill)


def parse_rich_text(line: str) -> list[tuple[str, bool]]:
    parts: list[tuple[str, bool]] = []
    for chunk in re.split(r"(\[\[.*?\]\])", line):
        if not chunk:
            continue
        if chunk.startswith("[[") and chunk.endswith("]]"):
            parts.append((chunk[2:-2], True))
        else:
            parts.append((chunk, False))
    return parts


def measure_rich_line(draw: ImageDraw.ImageDraw, parts: list[tuple[str, bool]], normal_font, focus_font) -> tuple[int, int]:
    width = 0
    top = 0
    bottom = 0
    for text, is_focus in parts:
        current_font = focus_font if is_focus else normal_font
        bbox = draw.textbbox((0, 0), text, font=current_font)
        width += bbox[2] - bbox[0]
        top = min(top, bbox[1])
        bottom = max(bottom, bbox[3])
    return width, bottom - top


def draw_rich_line(
    draw: ImageDraw.ImageDraw,
    x: int,
    y: int,
    line: str,
    normal_font,
    focus_font,
    normal_fill,
    focus_fill,
    shadow_fill,
) -> int:
    parts = parse_rich_text(line)
    cx = x
    _, height = measure_rich_line(draw, parts, normal_font, focus_font)
    for text, is_focus in parts:
        current_font = focus_font if is_focus else normal_font
        current_fill = focus_fill if is_focus else normal_fill
        draw_text_with_shadow(draw, (cx, y), text, current_font, current_fill, shadow_fill, shadow_offset=2 if not is_focus else 3)
        bbox = draw.textbbox((cx, y), text, font=current_font)
        cx = bbox[2]
    return y + height


def render_title(draw: ImageDraw.ImageDraw, spec: SlideSpec) -> None:
    title_font = font(FONT_SERIF, 72 if spec.layout == "center-close" else 78)
    title_focus_font = font(FONT_SANS_BOLD, 124 if spec.layout != "center-close" else 118)
    subtitle_font = font(FONT_SANS, 34)
    body_font = font(FONT_SANS, 34)
    chip_font = font(FONT_SANS_BOLD, 30)

    if spec.layout in {"left", "left-panel", "right-flow", "left-dark"}:
        tx = 120
        ty = 126
    elif spec.layout == "right-panel":
        tx = 980
        ty = 126
    elif spec.layout == "bottom-cards":
        tx = 130
        ty = 126
    else:
        tx = 380
        ty = 288

    fill = hex_rgba(PALETTE["warm_white"] if spec.dark else PALETTE["ink"])
    accent_fill = hex_rgba(spec.accent)
    muted_fill = hex_rgba(PALETTE["mist_blue"] if spec.dark else PALETTE["taupe"])
    shadow_fill = (4, 11, 24, 110) if spec.dark else (255, 250, 245, 130)

    title_lines = spec.title.split("\n")
    current_y = ty
    for idx, line in enumerate(title_lines):
        current_y = draw_rich_line(
            draw,
            tx,
            current_y,
            line,
            title_font,
            title_focus_font,
            fill,
            fill,
            shadow_fill,
        )
        current_y += 10 if idx == len(title_lines) - 1 else 18
    after_title = current_y

    if spec.subtitle:
        after_title = draw_text_block(draw, tx, after_title + 28, [spec.subtitle], subtitle_font, muted_fill, 16)

    if spec.layout == "right-flow":
        chip_y = after_title + 46
        chip_x = tx
        for label in spec.body:
            bbox = draw.textbbox((0, 0), label, font=chip_font)
            w = bbox[2] - bbox[0] + 58
            draw.rounded_rectangle((chip_x, chip_y, chip_x + w, chip_y + 66), radius=30, fill=hex_rgba("#f2e5cf", 250))
            draw.text((chip_x + 28, chip_y + 13), label, font=chip_font, fill=hex_rgba(PALETTE["ink"]))
            chip_x += w + 18
            if chip_x > 860:
                chip_x = tx
                chip_y += 82
        return

    if spec.layout == "bottom-cards":
        return

    if spec.layout == "center-close":
        draw.rounded_rectangle((610, 720, 1310, 794), radius=36, fill=hex_rgba("#f4e0bd", 250))
        draw.text((670, 738), spec.body[0], font=chip_font, fill=hex_rgba(PALETTE["ink"]))
        return

    body_y = after_title + 46
    for i, line in enumerate(spec.body):
        box_y = body_y + i * 98
        if spec.layout in {"left-panel", "right-panel", "left-dark"}:
            bg = (9, 19, 36, 178) if spec.dark else (248, 243, 236, 232)
            stroke = (178, 199, 224, 70) if spec.dark else (220, 204, 188, 255)
            draw.rounded_rectangle((tx, box_y, tx + 720, box_y + 70), radius=22, fill=bg, outline=stroke, width=1)
            draw.ellipse((tx + 18, box_y + 24, tx + 34, box_y + 40), fill=accent_fill)
            draw.text((tx + 54, box_y + 14), line, font=body_font, fill=fill)
        else:
            draw.text((tx, box_y), line, font=body_font, fill=fill)


def draw_bottom_cards(draw: ImageDraw.ImageDraw, spec: SlideSpec) -> None:
    if spec.layout != "bottom-cards":
        return

    card_font = font(FONT_SANS_BOLD, 34)
    desc_font = font(FONT_SANS, 24)
    cards = [
        ("振り返り支援", "過去の自分と、静かに再会できる"),
        ("自己理解", "感情や傾向を、あとから見つけられる"),
        ("分身AIの土台", "会話できる未来へつながる一次データ"),
    ]
    x = 130
    y = 688
    for title, desc in cards:
        draw.rounded_rectangle((x, y, x + 500, y + 206), radius=38, fill=(248, 241, 233, 224))
        draw.ellipse((x + 30, y + 30, x + 52, y + 52), fill=hex_rgba(spec.accent))
        draw.text((x + 70, y + 18), title, font=card_font, fill=hex_rgba(PALETTE["ink"]))
        draw.text((x + 32, y + 96), desc, font=desc_font, fill=hex_rgba(PALETTE["taupe"]))
        x += 550


def add_screenshot(base: Image.Image, spec: SlideSpec) -> None:
    if not spec.screenshot or not spec.screenshot_box:
        return

    shot = fit_cover(ASSETS / spec.screenshot, (spec.screenshot_box[2] - spec.screenshot_box[0], spec.screenshot_box[3] - spec.screenshot_box[1]))
    if spec.screenshot == "memory-space-ui.png":
        shot = ImageEnhance.Brightness(shot).enhance(1.8)
        shot = ImageEnhance.Contrast(shot).enhance(1.12)
    frame = Image.new("RGBA", base.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(frame)
    x1, y1, x2, y2 = spec.screenshot_box
    draw.rounded_rectangle((x1 - 16, y1 - 16, x2 + 16, y2 + 16), radius=34, fill=(3, 10, 24, 170))
    draw.rounded_rectangle((x1 - 8, y1 - 8, x2 + 8, y2 + 8), radius=28, outline=(196, 219, 245, 110), width=2)
    base.alpha_composite(frame)
    rounded_mask = Image.new("L", (shot.width, shot.height), 0)
    ImageDraw.Draw(rounded_mask).rounded_rectangle((0, 0, shot.width, shot.height), radius=22, fill=255)
    base.paste(shot, (x1, y1), rounded_mask)


def render_slide(spec: SlideSpec) -> Path:
    bg = fit_cover(ASSETS / spec.background, (WIDTH, HEIGHT))
    slide = add_overlay(bg, spec.dark, spec.layout)
    add_screenshot(slide, spec)
    draw = ImageDraw.Draw(slide)
    render_title(draw, spec)
    draw_bottom_cards(draw, spec)

    if spec.filename == "01_cover.png":
        label_font = font(FONT_LABEL, 28)
        draw.text((126, 96), "SERVICE INTRODUCTION", font=label_font, fill=hex_rgba(PALETTE["taupe"]))

    if spec.filename == "04_experience.png":
        chip_font = font(FONT_SANS_BOLD, 24)
        for i, label in enumerate(("カテゴリ", "感情", "年代", "タグ")):
            x = 120 + i * 146
            y = 738
            draw.rounded_rectangle((x, y, x + 124, y + 52), radius=26, fill=(10, 21, 40, 164), outline=(180, 201, 224, 70), width=1)
            draw.text((x + 20, y + 12), label, font=chip_font, fill=hex_rgba(PALETTE["warm_white"]))

    SLIDES.mkdir(parents=True, exist_ok=True)
    out = SLIDES / spec.filename
    slide.convert("RGB").save(out, quality=95)
    return out


def export_pdf(images: list[Path], dest: Path) -> None:
    dest.parent.mkdir(parents=True, exist_ok=True)
    pdf = canvas.Canvas(str(dest), pagesize=(WIDTH, HEIGHT))
    for image_path in images:
        pdf.drawImage(ImageReader(str(image_path)), 0, 0, width=WIDTH, height=HEIGHT)
        pdf.showPage()
    pdf.save()


def main() -> None:
    rendered = [render_slide(spec) for spec in SLIDES_SPEC]
    export_pdf(rendered, OUTPUT / "memoriru_service_intro_16x9.pdf")
    print(OUTPUT / "memoriru_service_intro_16x9.pdf")


if __name__ == "__main__":
    main()

import path from "node:path";

export const COLORS = {
  ink: "#1E1915",
  warm: "#F7F1EA",
  warm2: "#EFE3D5",
  sand: "#DCCDBB",
  gold: "#D8B98B",
  blue: "#B9CAD8",
  navy: "#081321",
  navySoft: "#102032",
  white: "#FFFDF9",
  muted: "#6E6258",
  line: "#D9CABB",
};

export const FONTS = {
  title: "Aptos Display",
  body: "Aptos",
};

export function asset(ctx, file) {
  return path.join(ctx.assetDir, file);
}

export function addBackground(slide, ctx, fill) {
  return ctx.addShape(slide, {
    name: "background",
    left: 0,
    top: 0,
    width: ctx.W,
    height: ctx.H,
    fill,
  });
}

export async function addFullBleedImage(slide, ctx, file) {
  return ctx.addImage(slide, {
    name: "hero-image",
    path: asset(ctx, file),
    left: 0,
    top: 0,
    width: ctx.W,
    height: ctx.H,
    fit: "cover",
  });
}

export function addPanel(slide, ctx, { left, top, width, height, fill, lineFill = fill, lineWidth = 0, name = "panel" }) {
  return ctx.addShape(slide, {
    name,
    left,
    top,
    width,
    height,
    fill,
    line: ctx.line(lineFill, lineWidth),
  });
}

export function addRule(slide, ctx, { left, top, width, fill = COLORS.line, height = 1, name = "rule" }) {
  return ctx.addShape(slide, {
    name,
    left,
    top,
    width,
    height,
    fill,
    line: ctx.line(fill, 0),
  });
}

export function addKicker(slide, ctx, { text, left, top, dark = false, accent = COLORS.gold, width = 220, index = "01" }) {
  ctx.addShape(slide, {
    name: `kicker-${index}-marker`,
    left,
    top: top + 4,
    width: 10,
    height: 10,
    fill: accent,
    line: ctx.line(accent, 0),
  });
  return ctx.addText(slide, {
    name: `kicker-${index}-label`,
    text,
    left: left + 18,
    top,
    width,
    height: 18,
    fontSize: 11,
    bold: true,
    color: dark ? COLORS.white : COLORS.muted,
    face: FONTS.body,
    valign: "mid",
  });
}

export function addTitle(slide, ctx, { text, left, top, width, height, size = 50, dark = false, name = "title" }) {
  return ctx.addText(slide, {
    name,
    text,
    left,
    top,
    width,
    height,
    fontSize: size,
    bold: true,
    color: dark ? COLORS.white : COLORS.ink,
    face: FONTS.title,
  });
}

export function addBody(slide, ctx, { text, left, top, width, height, size = 21, dark = false, color, name = "body" }) {
  return ctx.addText(slide, {
    name,
    text,
    left,
    top,
    width,
    height,
    fontSize: size,
    color: color ?? (dark ? "#E9E7E1" : COLORS.muted),
    face: FONTS.body,
  });
}

export function addChip(slide, ctx, { text, left, top, width, fill, color, dark = false, name = "chip" }) {
  addPanel(slide, ctx, {
    name,
    left,
    top,
    width,
    height: 28,
    fill: fill ?? (dark ? COLORS.navySoft : COLORS.white),
    lineFill: dark ? COLORS.navySoft : COLORS.sand,
    lineWidth: dark ? 0 : 1,
  });
  return ctx.addText(slide, {
    name: `${name}-text`,
    text,
    left: left + 12,
    top: top + 4,
    width: width - 24,
    height: 18,
    fontSize: 12,
    bold: true,
    color: color ?? (dark ? COLORS.white : COLORS.ink),
    face: FONTS.body,
    valign: "mid",
  });
}

export function addCard(slide, ctx, {
  left,
  top,
  width,
  height,
  title,
  body,
  fill = COLORS.white,
  titleColor = COLORS.ink,
  bodyColor = COLORS.muted,
  titleSize = 22,
  bodySize = 16,
  name = "card",
}) {
  addPanel(slide, ctx, {
    name,
    left,
    top,
    width,
    height,
    fill,
    lineFill: COLORS.line,
    lineWidth: 1,
  });
  ctx.addText(slide, {
    name: `${name}-title`,
    text: title,
    left: left + 18,
    top: top + 16,
    width: width - 36,
    height: 34,
    fontSize: titleSize,
    bold: true,
    color: titleColor,
    face: FONTS.title,
  });
  ctx.addText(slide, {
    name: `${name}-body`,
    text: body,
    left: left + 18,
    top: top + 56,
    width: width - 36,
    height: height - 70,
    fontSize: bodySize,
    color: bodyColor,
    face: FONTS.body,
  });
}

export function addFooter(slide, ctx, { text, page, dark = false }) {
  const ruleFill = dark ? "#314054" : COLORS.line;
  addRule(slide, ctx, {
    left: 72,
    top: 680,
    width: 1136,
    fill: ruleFill,
    name: "footer-rule",
  });
  ctx.addText(slide, {
    name: "footer-source",
    text,
    left: 72,
    top: 688,
    width: 980,
    height: 18,
    fontSize: 10,
    color: dark ? "#A9B5C3" : COLORS.muted,
    face: FONTS.body,
    valign: "mid",
  });
  ctx.addText(slide, {
    name: "footer-page",
    text: page,
    left: 1132,
    top: 686,
    width: 76,
    height: 20,
    fontSize: 11,
    bold: true,
    color: dark ? COLORS.white : COLORS.ink,
    face: FONTS.body,
    align: "right",
    valign: "mid",
  });
}

export async function addImageFrame(slide, ctx, { left, top, width, height, file, label, fill = COLORS.navySoft, border = COLORS.blue }) {
  addPanel(slide, ctx, {
    name: "screen-frame",
    left,
    top,
    width,
    height,
    fill,
    lineFill: border,
    lineWidth: 1,
  });
  await ctx.addImage(slide, {
    name: "screen-image",
    path: asset(ctx, file),
    left: left + 14,
    top: top + 14,
    width: width - 28,
    height: height - 54,
    fit: "cover",
  });
  addPanel(slide, ctx, {
    name: "screen-label-bg",
    left: left + 16,
    top: top + height - 28,
    width: 138,
    height: 20,
    fill: COLORS.blue,
    lineFill: COLORS.blue,
    lineWidth: 0,
  });
  ctx.addText(slide, {
    name: "screen-label",
    text: label,
    left: left + 26,
    top: top + height - 26,
    width: 118,
    height: 14,
    fontSize: 10,
    bold: true,
    color: fill === COLORS.navySoft ? COLORS.navy : COLORS.navy,
    face: FONTS.body,
    valign: "mid",
  });
}

export async function addScreenFrame(slide, ctx, options) {
  return addImageFrame(slide, ctx, options);
}

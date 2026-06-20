import {
  COLORS,
  addBody,
  addChip,
  addFooter,
  addKicker,
  addPanel,
  addTitle,
} from "./theme.mjs";

export async function slide05(presentation, ctx) {
  const slide = presentation.slides.add();

  await ctx.addImage(slide, {
    name: "privacy-image-right",
    path: `${ctx.assetDir}/privacy-sanctuary.png`,
    left: 590,
    top: 0,
    width: 690,
    height: 650,
    fit: "cover",
  });
  addPanel(slide, ctx, {
    left: 0,
    top: 0,
    width: 590,
    height: 720,
    fill: COLORS.warm,
    lineFill: COLORS.warm,
    lineWidth: 0,
    name: "privacy-panel",
  });

  addKicker(slide, ctx, {
    text: "PRIVACY BY DESIGN",
    left: 78,
    top: 68,
    width: 180,
    accent: COLORS.gold,
    index: "05",
  });

  addTitle(slide, ctx, {
    text: "見せたくない記憶は、\n最初から別導線で保護",
    left: 78,
    top: 116,
    width: 430,
    height: 144,
    size: 40,
    name: "privacy-title",
  });

  addBody(slide, ctx, {
    text: "書けるけれど、見せなくていい。\nその前提をUXと認可フローで担保します。",
    left: 78,
    top: 260,
    width: 392,
    height: 54,
    size: 18,
    name: "privacy-note",
  });

  addChip(slide, ctx, { text: "通常記憶とsecret記憶を混在させない", left: 78, top: 372, width: 330, fill: COLORS.white, name: "privacy-chip-1" });
  addChip(slide, ctx, { text: "専用アンロック時だけ表示", left: 78, top: 414, width: 250, fill: COLORS.white, name: "privacy-chip-2" });
  addChip(slide, ctx, { text: "アカウントとは別の保護フロー", left: 78, top: 456, width: 290, fill: COLORS.white, name: "privacy-chip-3" });

  addFooter(slide, ctx, {
    text: "Source: secret unlock design / API contract / generated privacy visual",
    page: "05",
  });

  return slide;
}

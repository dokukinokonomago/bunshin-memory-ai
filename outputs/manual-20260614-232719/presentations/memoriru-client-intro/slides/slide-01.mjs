import {
  COLORS,
  addBody,
  addChip,
  addFooter,
  addKicker,
  addPanel,
  addTitle,
} from "./theme.mjs";

export async function slide01(presentation, ctx) {
  const slide = presentation.slides.add();

  await ctx.addImage(slide, {
    name: "cover-image-right",
    path: `${ctx.assetDir}/hero-memory-shore.png`,
    left: 610,
    top: 0,
    width: 670,
    height: 650,
    fit: "cover",
  });
  addPanel(slide, ctx, {
    left: 0,
    top: 0,
    width: 610,
    height: 720,
    fill: COLORS.warm,
    lineFill: COLORS.warm,
    lineWidth: 0,
    name: "cover-panel",
  });

  addKicker(slide, ctx, {
    text: "PRODUCT INTRODUCTION",
    left: 78,
    top: 70,
    width: 240,
    accent: COLORS.gold,
    index: "01",
  });

  addTitle(slide, ctx, {
    text: "記憶管理AIアプリ\nメモリル",
    left: 78,
    top: 122,
    width: 460,
    height: 150,
    size: 54,
    name: "cover-title",
  });

  addBody(slide, ctx, {
    text: "会話から、未来に残せる人生データへ\n日々の感情・出来事・文脈を、あとから探索できる形で蓄積します。",
    left: 78,
    top: 286,
    width: 438,
    height: 72,
    size: 19,
    name: "cover-body",
  });

  addChip(slide, ctx, {
    text: "会話で残す",
    left: 78,
    top: 430,
    width: 126,
    fill: COLORS.white,
    name: "chip-1",
  });
  addChip(slide, ctx, {
    text: "感情で整理",
    left: 216,
    top: 430,
    width: 136,
    fill: COLORS.white,
    name: "chip-2",
  });
  addChip(slide, ctx, {
    text: "秘匿記憶を保護",
    left: 364,
    top: 430,
    width: 154,
    fill: COLORS.white,
    name: "chip-3",
  });

  addBody(slide, ctx, {
    text: "記録 -> 構造化 -> 探索 -> 分身AI",
    left: 78,
    top: 504,
    width: 320,
    height: 26,
    size: 17,
    color: COLORS.ink,
    name: "cover-rail",
  });

  addFooter(slide, ctx, {
    text: "Source: メモリルLP / memory-space設計 / generated concept visuals",
    page: "01",
  });

  return slide;
}

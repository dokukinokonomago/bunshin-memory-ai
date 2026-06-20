import {
  COLORS,
  addBody,
  addFooter,
  addKicker,
  addPanel,
  addTitle,
} from "./theme.mjs";

export async function slide07(presentation, ctx) {
  const slide = presentation.slides.add();

  await ctx.addImage(slide, {
    name: "close-image-right",
    path: `${ctx.assetDir}/future-companion.png`,
    left: 624,
    top: 0,
    width: 656,
    height: 650,
    fit: "cover",
  });
  addPanel(slide, ctx, {
    left: 0,
    top: 0,
    width: 624,
    height: 720,
    fill: COLORS.warm,
    lineFill: COLORS.warm,
    lineWidth: 0,
    name: "close-panel",
  });

  addKicker(slide, ctx, {
    text: "CLOSING",
    left: 78,
    top: 70,
    width: 120,
    accent: COLORS.gold,
    index: "07",
  });

  addTitle(slide, ctx, {
    text: "いま残す記憶が、\n未来の分身AIを育てる",
    left: 78,
    top: 126,
    width: 460,
    height: 156,
    size: 46,
    name: "close-title",
  });

  addBody(slide, ctx, {
    text: "メモリルは、日々の気持ちを静かに蓄積し、\nあとで探索できる人生データへ変えるプロダクトです。\n\n記録 -> 整理 -> 探索 -> 分身AI",
    left: 78,
    top: 296,
    width: 404,
    height: 144,
    size: 19,
    name: "close-body",
  });

  addFooter(slide, ctx, {
    text: "Memoriru product overview / generated closing visual",
    page: "07",
  });

  return slide;
}

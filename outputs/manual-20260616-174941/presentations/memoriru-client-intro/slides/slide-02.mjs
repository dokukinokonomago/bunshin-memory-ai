import {
  COLORS,
  addBackground,
  addBody,
  addCard,
  addFooter,
  addKicker,
  addRule,
  addTitle,
} from "./theme.mjs";

export async function slide02(presentation, ctx) {
  const slide = presentation.slides.add();

  addBackground(slide, ctx, COLORS.warm);
  addKicker(slide, ctx, {
    text: "WHY NOW",
    left: 78,
    top: 68,
    width: 120,
    accent: COLORS.gold,
    index: "02",
  });

  addTitle(slide, ctx, {
    text: "思い出は残っても、\n気持ちは残りにくい",
    left: 78,
    top: 116,
    width: 470,
    height: 132,
    size: 46,
    name: "problem-title",
  });

  addBody(slide, ctx, {
    text: "必要なのは、残すことではなく、\nあとで思い出せる形で積み上げることです。",
    left: 78,
    top: 260,
    width: 390,
    height: 56,
    size: 18,
    name: "problem-note",
  });

  addRule(slide, ctx, {
    left: 78,
    top: 346,
    width: 258,
    fill: COLORS.gold,
    height: 3,
    name: "problem-accent",
  });

  addCard(slide, ctx, {
    left: 566,
    top: 90,
    width: 632,
    height: 136,
    title: "続かない記録",
    body: "日記は負荷が高く、忙しい日ほど残したいことが途切れやすい。",
    fill: COLORS.white,
    name: "problem-card-1",
  });
  addCard(slide, ctx, {
    left: 566,
    top: 248,
    width: 632,
    height: 136,
    title: "文脈がこぼれる",
    body: "写真やメモだけでは、その時の感情や背景が後から抜け落ちる。",
    fill: "#F4ECE2",
    name: "problem-card-2",
  });
  addCard(slide, ctx, {
    left: 566,
    top: 406,
    width: 632,
    height: 136,
    title: "探し直せない",
    body: "出来事が点で散らばると、自分史として振り返りづらくなる。",
    fill: COLORS.white,
    name: "problem-card-3",
  });

  addFooter(slide, ctx, {
    text: "Source: メモリルLP copy and product positioning",
    page: "02",
  });

  return slide;
}

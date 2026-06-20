import {
  COLORS,
  addBackground,
  addBody,
  addFooter,
  addKicker,
  addRule,
  addScreenFrame,
  addTitle,
} from "./theme.mjs";

export async function slide04(presentation, ctx) {
  const slide = presentation.slides.add();

  addBackground(slide, ctx, COLORS.navy);
  addKicker(slide, ctx, {
    text: "PRODUCT EXPERIENCE",
    left: 78,
    top: 68,
    width: 190,
    dark: true,
    accent: COLORS.blue,
    index: "04",
  });

  addTitle(slide, ctx, {
    text: "一覧でなく、記憶の宇宙として振り返れる",
    left: 78,
    top: 116,
    width: 420,
    height: 144,
    size: 42,
    dark: true,
    name: "experience-title",
  });

  addBody(slide, ctx, {
    text: "大カテゴリから1つの記憶まで、\nズームしながら自分史をたどれる体験です。",
    left: 78,
    top: 286,
    width: 360,
    height: 54,
    size: 18,
    dark: true,
    name: "experience-note",
  });

  addRule(slide, ctx, {
    left: 78,
    top: 374,
    width: 280,
    fill: COLORS.blue,
    height: 2,
    name: "experience-rule",
  });

  addBody(slide, ctx, {
    text: "01  カテゴリが星団としてまとまる",
    left: 78,
    top: 410,
    width: 360,
    height: 28,
    size: 18,
    dark: true,
    name: "xp-1",
  });
  addBody(slide, ctx, {
    text: "02  感情が色で浮かび上がる",
    left: 78,
    top: 454,
    width: 320,
    height: 28,
    size: 18,
    dark: true,
    name: "xp-2",
  });
  addBody(slide, ctx, {
    text: "03  俯瞰と深掘りを往復できる",
    left: 78,
    top: 498,
    width: 340,
    height: 28,
    size: 18,
    dark: true,
    name: "xp-3",
  });

  await addScreenFrame(slide, ctx, {
    left: 516,
    top: 96,
    width: 686,
    height: 514,
    file: "memory-space-ui.png",
    label: "Actual product screen",
  });

  addFooter(slide, ctx, {
    text: "Source: local memory-space screen and architecture design",
    page: "04",
    dark: true,
  });

  return slide;
}

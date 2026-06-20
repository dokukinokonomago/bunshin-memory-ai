import {
  COLORS,
  addBackground,
  addBody,
  addCard,
  addFooter,
  addImageFrame,
  addKicker,
  addTitle,
} from "./theme.mjs";

export async function slide06(presentation, ctx) {
  const slide = presentation.slides.add();

  addBackground(slide, ctx, "#FBF7F2");
  addKicker(slide, ctx, {
    text: "CLIENT VALUE",
    left: 78,
    top: 66,
    width: 140,
    accent: COLORS.gold,
    index: "06",
  });

  addTitle(slide, ctx, {
    text: "記録アプリではなく、\n自己理解を深める基盤になる",
    left: 78,
    top: 112,
    width: 470,
    height: 142,
    size: 40,
    name: "value-title",
  });

  addBody(slide, ctx, {
    text: "継続しやすい入力、あとで見返しやすい構造、\nそして将来の分身AIへつながる一次データが価値になります。",
    left: 78,
    top: 260,
    width: 460,
    height: 60,
    size: 17,
    name: "value-note",
  });

  await addImageFrame(slide, ctx, {
    left: 778,
    top: 86,
    width: 424,
    height: 238,
    file: "future-companion.png",
    label: "Future vision",
  });

  addCard(slide, ctx, {
    left: 78,
    top: 378,
    width: 352,
    height: 190,
    title: "振り返り支援",
    body: "過去の自分を、出来事だけでなく感情ごとたどれる。\n自己理解や内省の質が上がる。",
    fill: COLORS.white,
    name: "value-card-1",
  });
  addCard(slide, ctx, {
    left: 454,
    top: 378,
    width: 352,
    height: 190,
    title: "継続しやすい記録",
    body: "書くより話すことに近い体験なので、日記より習慣化しやすい。",
    fill: "#F4ECE2",
    name: "value-card-2",
  });
  addCard(slide, ctx, {
    left: 830,
    top: 378,
    width: 372,
    height: 190,
    title: "将来の分身AI基盤",
    body: "会話・感情・記憶の蓄積が、将来のパーソナルAI精度を支える。",
    fill: COLORS.white,
    name: "value-card-3",
  });

  addFooter(slide, ctx, {
    text: "Source: product concept, LP messaging, generated future visual",
    page: "06",
  });

  return slide;
}

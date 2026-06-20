import {
  COLORS,
  addBackground,
  addBody,
  addCard,
  addChip,
  addFooter,
  addImageFrame,
  addKicker,
  addTitle,
} from "./theme.mjs";

export async function slide03(presentation, ctx) {
  const slide = presentation.slides.add();

  addBackground(slide, ctx, "#FBF7F2");
  addKicker(slide, ctx, {
    text: "HOW IT WORKS",
    left: 78,
    top: 66,
    width: 160,
    accent: COLORS.gold,
    index: "03",
  });

  addTitle(slide, ctx, {
    text: "メモリルは、会話を\n“使える記憶”に変える",
    left: 78,
    top: 114,
    width: 432,
    height: 138,
    size: 42,
    name: "workflow-title",
  });

  addBody(slide, ctx, {
    text: "会話のしやすさはそのままに、\nあとから振り返れる構造へ変換します。",
    left: 78,
    top: 252,
    width: 350,
    height: 50,
    size: 18,
    name: "workflow-note",
  });

  addCard(slide, ctx, {
    left: 78,
    top: 338,
    width: 356,
    height: 100,
    title: "01 話す",
    body: "その日の出来事や感情を自然な言葉で残す。",
    fill: COLORS.white,
    titleSize: 20,
    name: "workflow-step-1",
  });
  addCard(slide, ctx, {
    left: 78,
    top: 450,
    width: 356,
    height: 100,
    title: "02 構造化する",
    body: "AIが感情や年代を整理する。",
    fill: "#F4ECE2",
    titleSize: 20,
    name: "workflow-step-2",
  });
  addCard(slide, ctx, {
    left: 78,
    top: 562,
    width: 356,
    height: 100,
    title: "03 探索する",
    body: "後から振り返り、将来のAI活用へつなげる。",
    fill: COLORS.white,
    titleSize: 20,
    name: "workflow-step-3",
  });

  await addImageFrame(slide, ctx, {
    left: 512,
    top: 88,
    width: 690,
    height: 480,
    file: "workflow-memory-map.png",
    label: "Concept visual",
  });

  addChip(slide, ctx, { text: "感情", left: 520, top: 590, width: 76, fill: COLORS.navy, color: COLORS.white, dark: true, name: "attr-1" });
  addChip(slide, ctx, { text: "年代", left: 606, top: 590, width: 76, fill: COLORS.navy, color: COLORS.white, dark: true, name: "attr-2" });
  addChip(slide, ctx, { text: "カテゴリ", left: 692, top: 590, width: 92, fill: COLORS.navy, color: COLORS.white, dark: true, name: "attr-3" });
  addChip(slide, ctx, { text: "タグ", left: 794, top: 590, width: 76, fill: COLORS.navy, color: COLORS.white, dark: true, name: "attr-4" });
  addChip(slide, ctx, { text: "秘匿設定", left: 880, top: 590, width: 104, fill: COLORS.navy, color: COLORS.white, dark: true, name: "attr-5" });

  addFooter(slide, ctx, {
    text: "Source: memory-space設計 / API契約 / generated workflow visual",
    page: "03",
  });

  return slide;
}

---
version: alpha
name: The Signal Box
description: Dot.Agents. A digital workforce run the way a signal box runs a line, every agent on its own track and every decision at a signal.
colors:
  primary: "#F0C23A"
  on-primary: "#1A1400"
  secondary: "#9A98A6"
  neutral: "#17151E"
  surface: "#121018"
  raised: "#211E2A"
  border: "#2A2733"
  rule: "#1F1C27"
  control: "#433F50"
  text: "#F5F4F7"
  clear: "#4FBF94"
  stop: "#EF6A7E"
  hold: "#F0C23A"
  running: "#7FB2EC"
typography:
  title:
    fontFamily: Public Sans
    fontSize: 24px
    fontWeight: 600
    lineHeight: 30px
  section:
    fontFamily: Public Sans
    fontSize: 16px
    fontWeight: 600
    lineHeight: 22px
  body:
    fontFamily: Public Sans
    fontSize: 14px
    fontWeight: 400
    lineHeight: 20px
  small:
    fontFamily: Public Sans
    fontSize: 12px
    fontWeight: 400
    lineHeight: 16px
  figure:
    fontFamily: IBM Plex Mono
    fontSize: 20px
    fontWeight: 500
    lineHeight: 26px
rounded:
  card: 6px
  control: 6px
  chip: 4px
spacing:
  gap: 16px
  pad: 24px
  row: 44px
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    rounded: "{rounded.control}"
    height: 34px
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.card}"
---

## Overview

A business supervises its agents the way a signal box supervises a line. Trains run on their own. A person watches a panel, sets signals, and the interlocking makes a dangerous move impossible even if someone asks for it. That is exactly what Dot.Agents has to feel like: work moving by itself, every run visible as a line, every decision held at a signal, and rules enforced by the machine rather than by hope.

The metaphor lives in the drawing, never in the words. No page says train, track or signal. People read "Waiting for approval", and see a held lamp on a line.

**Screen question:** what are my agents doing, and what needs me?

**The face:** the one accent is the brand's gold, used as a 1px selection line and as the colour of anything held for a person. Every figure is set in IBM Plex Mono, so counts, money and times stack.

**The element only this product has:** the line. A run is drawn as one line with a stop for each step. A step waiting for a person is a held signal on that line. It appears on the run page, the agent profile, the execution centre and the workflow builder, so one picture means one thing everywhere.

Style dealt by the taste skill's app chapter: data terminal, dark, cards darker than the ground, signs instead of colour, 1px line for selection. One change, stated: its icon rail becomes a labelled sidebar, because the audit found the product's pages were hidden, and a business owner needs words.

## Colors

Colour goes on state, not on chrome. Under 3% of a screen.

- **Ground and surface:** near-black tinted from the brand's ink, cards one step darker than the ground.
- **Gold:** selection lines, the primary button, and anything held for a person. Never a decoration.
- **Clear, stop, running:** status only, always with a shape and a word.
- **Light theme:** same roles, same rules. Dark is the default.

## Typography

Public Sans for words, IBM Plex Mono for figures, ids and times. Sentence case. No tracked capitals. Page titles 24px at most: no hero headers inside the app.

## Layout

Sidebar 232px with words, grouped by what a person is doing: Today, Workforce, Build, Oversight, Business. Content width 1200px at most. One gap value, 16px. Every list is rows on rules, numbers right-aligned, no zebra. On a phone the sidebar is a drawer, closed on load, and rows become two lines.

## Elevation & Depth

Flat. One 1px line or one tone step. Only sheets and dialogs cast a shadow.

## Shapes

6px on cards and controls, 4px on chips. Lamps are the only circles: a run's state is a lamp, and each state has its own shape as well as its colour.

## Components

- **Lamp:** a state as shape, colour and word. Running pulses gently and stops under reduced motion.
- **Line:** the run drawn as stops. Horizontal on a desktop, vertical on a phone.
- **Row:** the unit of every list. 44px.
- **Stat:** a label and a mono figure. No icon, no tile, no sparkline, no invented delta.
- **Card for approval:** rendered by the system from typed arguments. Model text is labelled as model text and sits apart.

## Do's and Don'ts

- Do show zero as zero and missing as a dash.
- Do put the next action one click from the first screen.
- Do use the trade's words: "On duty", "Waiting for approval", "Stopped by budget".
- Don't use gradients, glows, glass, floating cards, avatar tiles with two letters, or an uppercase label over every number.
- Don't show a number that is not computed from real records.
- Don't let colour carry meaning alone.

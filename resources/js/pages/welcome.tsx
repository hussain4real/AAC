// @ts-nocheck
/* eslint-disable */
import React from 'react';
import { Head } from '@inertiajs/react';
import '../../css/landing.css';

/* ============================================================
   MAACC Landing — shared lib: icons, theme, reveal, data
   ============================================================ */
const { useState, useEffect, useRef, useCallback, useMemo, createContext, useContext } = React;

/* ---------------- Icons (MAAC set + extras) ---------------- */
const ICON_PATHS = {
  agents:'M12 3a3 3 0 0 1 3 3v1h1a3 3 0 0 1 3 3v1m-10-8a3 3 0 0 0-3 3v1H5a3 3 0 0 0-3 3v1m7 8v-3m4 3v-3M8 21h8M9 11h.01M15 11h.01M8 7h8a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z',
  tools:'M14.7 6.3a4 4 0 0 1-5.4 5.4l-5.6 5.6a2 2 0 1 0 2.8 2.8l5.6-5.6a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.1-.4-.4-2.1 2.2-2.8Z',
  sdk:'M8 9l-3 3 3 3m8-6l3 3-3 3m-3-9-4 12',
  runs:'M4 5h16M4 12h16M4 19h10M19 16l2 2-2 2',
  llm:'M12 3l2.5 5.5L20 11l-5.5 2.5L12 19l-2.5-5.5L4 11l5.5-2.5L12 3Z',
  governance:'M12 3 4 6v6c0 4.5 3.3 7.6 8 9 4.7-1.4 8-4.5 8-9V6l-8-3Z',
  settings:'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm8.4 3a8.4 8.4 0 0 0-.1-1.3l2-1.6-2-3.4-2.4 1a8 8 0 0 0-2.2-1.3L15 1h-4l-.6 2.6A8 8 0 0 0 8.2 4.9l-2.4-1-2 3.4 2 1.6a8.4 8.4 0 0 0 0 2.6l-2 1.6 2 3.4 2.4-1a8 8 0 0 0 2.2 1.3L11 23h4l.6-2.6a8 8 0 0 0 2.2-1.3l2.4 1 2-3.4-2-1.6c.1-.4.2-.9.2-1.3Z',
  search:'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm6 13 4 4',
  chevdown:'M6 9l6 6 6-6',
  chevright:'M9 6l6 6-6 6',
  plus:'M12 5v14M5 12h14',
  check:'M5 12l5 5L20 6',
  check2:'M20 6 9 17l-5-5',
  checkCircle:'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm-3-10 2 2 4-4',
  x:'M6 6l12 12M18 6 6 18',
  alert:'M12 2 1 21h22L12 2Zm0 7v5m0 4h.01',
  clock:'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm0-15v5l3 2',
  key:'M14 7a4 4 0 1 1-5.7 5.6L3 18v3h3l1-1h2v-2h2l1.3-1.3A4 4 0 0 1 14 7Zm2 2h.01',
  sparkles:'M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8L12 3Zm6 9 .9 2.1L21 15l-2.1.9L18 18l-.9-2.1L15 15l2.1-.9L18 12Z',
  copy:'M9 9h10a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V10a1 1 0 0 1 1-1ZM5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1',
  refresh:'M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16m0 5v-5h5',
  external:'M14 4h6v6m0-6L10 14M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6',
  arrowRight:'M5 12h14m-6-6 6 6-6 6',
  arrowDown:'M12 5v14m-6-6 6 6 6-6',
  sun:'M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0-5v2m0 18v2M4.2 4.2l1.4 1.4m12.8 12.8 1.4 1.4M2 12h2m18 0h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
  moon:'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z',
  play:'M6 4.5v15a1 1 0 0 0 1.5.86l12-7.5a1 1 0 0 0 0-1.72l-12-7.5A1 1 0 0 0 6 4.5Z',
  pause:'M8 5h3v14H8zM13 5h3v14h-3z',
  user:'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0',
  doc:'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Zm0 0v5h5M9 13h6M9 17h6',
  code:'M8 9l-3 3 3 3m8-6 3 3-3 3M13 6l-2 12',
  layers:'M12 3 2 8l10 5 10-5-10-5Zm10 9-10 5L2 12m20 5-10 5L2 17',
  link:'M9 15l6-6m-4-3 1.5-1.5a4 4 0 0 1 5.7 5.7L16.5 12m-9 0L6 13.5a4 4 0 0 0 5.7 5.7L13 18',
  database:'M12 3c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3Zm8 3v6c0 1.7-3.6 3-8 3s-8-1.3-8-3V6m16 6v6c0 1.7-3.6 3-8 3s-8-1.3-8-3v-6',
  globe:'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm-9-10h18M12 2a14 14 0 0 1 0 20M12 2a14 14 0 0 0 0 20',
  book:'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Zm2 13h13',
  cpu:'M7 7h10v10H7zM9.5 9.5h5v5h-5zM9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2',
  bolt:'M13 2 4 14h6l-1 8 9-12h-6l1-8Z',
  flask:'M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-9V3M8 15h8',
  shield:'M12 3 4 6v6c0 4.5 3.3 7.6 8 9 4.7-1.4 8-4.5 8-9V6l-8-3Z',
  shieldCheck:'M12 3 4 6v6c0 4.5 3.3 7.6 8 9 4.7-1.4 8-4.5 8-9V6l-8-3Zm-3 9 2 2 4-4',
  send:'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z',
  eye:'M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
  power:'M12 4v8m5.5-5.5a8 8 0 1 1-11 0',
  pin:'M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
  flow:'M5 4h4v4H5zM15 8h4v4h-4zM5 16h4v4H5zM9 6h4a2 2 0 0 1 2 2M9 18h4a2 2 0 0 1 2-2',
  info:'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm0-14h.01M11 12h1v5h1',
  lock:'M6 11h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Zm2 0V8a4 4 0 0 1 8 0v3',
  download:'M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2',
  menu:'M4 6h16M4 12h16M4 18h16',
  gauge:'M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 0 4-4M5.5 18.5a9 9 0 1 1 13 0',
  route:'M6 19a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm12-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 0v3a5 5 0 0 1-5 5H9m-3-6V8a5 5 0 0 1 5-5h1',
  activity:'M22 12h-4l-3 9L9 3l-3 9H2',
  terminal:'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm3 4 3 3-3 3m6 0h4',
  git:'M12 3v6m0 6v6M6 12h12M9 9l6 6m0-6-6 6',
  stack:'M12 2 2 7l10 5 10-5-10-5Zm-10 9 10 5 10-5M2 15l10 5 10-5',
  building:'M3 21h18M5 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16M15 21V9h3a1 1 0 0 1 1 1v11M8 8h2m-2 4h2m-2 4h2',
  scale:'M12 3v18M7 21h10M6 7h12M6 7l-3 6a3 3 0 0 0 6 0L6 7Zm12 0-3 6a3 3 0 0 0 6 0l-3-6ZM12 5a2 2 0 1 0 0-2',
  fingerprint:'M12 10a2 2 0 0 1 2 2c0 3-.5 5-1.5 7M8 14c.5-3 .3-5 4-5M5 12a7 7 0 0 1 11-5.7M12 3a9 9 0 0 0-6.3 15.4',
};
function Icon({ name, size = 18, style = {}, strokeWidth = 1.7, fill = false, className = "" }) {
  const d = ICON_PATHS[name];
  if (!d) return null;
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" className={className}
      fill={fill ? "currentColor" : "none"} stroke={fill ? "none" : "currentColor"}
      strokeWidth={strokeWidth} strokeLinecap="round" strokeLinejoin="round"
      style={{ flexShrink: 0, display:"block", ...style }} aria-hidden="true">
      <path d={d} />
    </svg>
  );
}

/* ---------------- Theme ---------------- */
const ThemeCtx = createContext(null);
const useTheme = () => useContext(ThemeCtx);
function useThemeState() {
  const [theme, setTheme] = useState(() => {
    try { return localStorage.getItem("maacc-landing-theme") || "dark"; } catch (e) { return "dark"; }
  });
  useEffect(() => {
    document.documentElement.setAttribute("data-theme", theme);
    try { localStorage.setItem("maacc-landing-theme", theme); } catch (e) {}
  }, [theme]);
  const toggle = useCallback(() => setTheme(t => t === "dark" ? "light" : "dark"), []);
  return { theme, setTheme, toggle };
}

/* ---------------- Scroll reveal ---------------- */
function useReveal() {
  const ref = useRef(null);
  useEffect(() => {
    const el = ref.current; if (!el) return;
    if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) { el.classList.add("in"); return; }
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) { el.classList.add("in"); io.unobserve(el); } });
    }, { threshold: 0.14, rootMargin: "0px 0px -8% 0px" });
    io.observe(el);
    return () => io.disconnect();
  }, []);
  return ref;
}
function Reveal({ children, delay = 0, as = "div", className = "", style = {}, ...rest }) {
  const ref = useReveal();
  const Tag = as;
  const d = delay ? ` reveal-d${delay}` : "";
  return <Tag ref={ref} className={`reveal${d} ${className}`} style={style} {...rest}>{children}</Tag>;
}

/* fires callback once when element scrolls into view */
function useInViewOnce(cb, threshold = 0.3) {
  const ref = useRef(null);
  const fired = useRef(false);
  useEffect(() => {
    const el = ref.current; if (!el) return;
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting && !fired.current) { fired.current = true; cb(); } });
    }, { threshold });
    io.observe(el);
    return () => io.disconnect();
  }, []);
  return ref;
}

/* ---------------- Buttons / bits ---------------- */
function Btn({ children, variant = "primary", size = "md", icon, iconRight, href, onClick, style = {}, target, ...rest }) {
  const cls = `ls-btn ls-btn-${variant}${size === "sm" ? " ls-btn-sm" : ""}${size === "lg" ? " ls-btn-lg" : ""}`;
  const inner = <>{icon && <Icon name={icon} size={size === "sm" ? 16 : 18} strokeWidth={2} />}{children}{iconRight && <Icon name={iconRight} size={size === "sm" ? 16 : 18} strokeWidth={2} />}</>;
  if (href) return <a href={href} target={target} onClick={onClick} className={cls} style={style} {...rest}>{inner}</a>;
  return <button onClick={onClick} className={cls} style={style} {...rest}>{inner}</button>;
}

function CopyChip({ text, label, style = {} }) {
  const [copied, setCopied] = useState(false);
  const copy = () => { try { navigator.clipboard?.writeText(text); } catch(e){} setCopied(true); setTimeout(() => setCopied(false), 1400); };
  return (
    <button onClick={copy} className="ls-glass" style={{
      display:"inline-flex", alignItems:"center", gap:11, height:46, padding:"0 8px 0 16px", borderRadius:"var(--r-md)",
      cursor:"pointer", fontFamily:"var(--mono)", fontSize:14, color:"var(--text)", ...style }}>
      <span style={{ color:"var(--text-3)" }}>$</span>
      <span>{label || text}</span>
      <span style={{ display:"inline-flex", alignItems:"center", justifyContent:"center", width:30, height:30, borderRadius:6,
        background:"var(--surface-3)", color:copied ? "var(--teal-500)" : "var(--text-3)", transition:"color .2s" }}>
        <Icon name={copied ? "check" : "copy"} size={15} />
      </span>
    </button>
  );
}

function SectionHead({ kicker, title, sub, center, children }) {
  return (
    <div style={{ maxWidth: center ? 720 : 760, margin: center ? "0 auto" : 0, textAlign: center ? "center" : "left" }}>
      {kicker && <Reveal as="div" className="ls-kicker">{kicker}</Reveal>}
      {title && <Reveal as="h2" delay={1} className="ls-h2" style={{ textWrap:"balance" }}>{title}</Reveal>}
      {sub && <Reveal as="p" delay={2} className="ls-sub" style={{ marginLeft: center ? "auto" : 0, marginRight: center ? "auto" : 0 }}>{sub}</Reveal>}
      {children}
    </div>
  );
}

/* count-up when in view */
function CountUp({ end, suffix = "", prefix = "", decimals = 0, dur = 1400 }) {
  const [val, setVal] = useState(0);
  const ref = useInViewOnce(() => {
    if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) { setVal(end); return; }
    const t0 = performance.now();
    const tick = (t) => {
      const p = Math.min(1, (t - t0) / dur);
      const e = 1 - Math.pow(1 - p, 3);
      setVal(end * e);
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }, 0.5);
  const shown = decimals ? val.toFixed(decimals) : Math.round(val).toLocaleString();
  return <span ref={ref} className="tnum">{prefix}{shown}{suffix}</span>;
}

/* ---------------- MAACC data ---------------- */
const MAACC = {
  brand: { name:"MAACC", full:"Multi AI Agent Control Centre" },
  execModes: {
    hosted:{ label:"MAACC-hosted", icon:"cpu", tone:"var(--primary)" },
    client:{ label:"Client-side", icon:"link", tone:"var(--orange-500)" },
    http:{ label:"Remote HTTP", icon:"globe", tone:"var(--blue-400)" },
    connector:{ label:"Connector", icon:"layers", tone:"var(--blue-400)" },
    knowledge:{ label:"Knowledge", icon:"book", tone:"var(--teal-500)" },
    db:{ label:"Read-only DB", icon:"database", tone:"var(--amber-500)" },
  },
  llms: [
    { name:"GPT-4o", provider:"Azure OpenAI", ctx:"128K", tone:"var(--teal-500)" },
    { name:"Claude 3.7 Sonnet", provider:"AWS Bedrock", ctx:"200K", tone:"var(--orange-500)" },
    { name:"Gemini 1.5 Pro", provider:"Google Vertex AI", ctx:"1M", tone:"var(--blue-400)" },
    { name:"Llama 3.1 70B", provider:"On-Prem GPU", ctx:"128K", tone:"var(--primary)" },
    { name:"GPT-4o mini", provider:"Azure OpenAI", ctx:"128K", tone:"var(--teal-500)" },
    { name:"Claude 3.5 Haiku", provider:"AWS Bedrock", ctx:"200K", tone:"var(--orange-500)" },
  ],
  features: [
    { icon:"agents", title:"Agent builder", tone:"var(--primary)",
      desc:"Compose an agent from a system prompt, an approved model, and governed tools. Draft, version, test, publish." },
    { icon:"llm", title:"Approved LLM catalog", tone:"var(--teal-500)",
      desc:"One governed catalog across Azure, Bedrock, Vertex and on-prem GPUs. Per-project access, token & cost tracking." },
    { icon:"tools", title:"Tools & contracts", tone:"var(--orange-500)",
      desc:"Define a tool once as a typed contract. Six execution modes — from MAACC-hosted to client-side in your app." },
    { icon:"sdk", title:"SDK, drop-in", tone:"var(--blue-400)",
      desc:"Install, authenticate, register local handlers, invoke agents. Pause-and-resume handled for you." },
    { icon:"shieldCheck", title:"Governance built-in", tone:"var(--primary)",
      desc:"RBAC, model & tool policies, approvals, data-sensitivity classes, credential rotation — enforced centrally." },
    { icon:"activity", title:"Full observability", tone:"var(--teal-500)",
      desc:"Every run traced end-to-end: model, tokens, latency, tool calls, status. Auditable, exportable, alertable." },
  ],
  stats: [
    { end:1.2, decimals:1, suffix:"M", label:"agent runs / month", sub:"across connected apps" },
    { end:280, suffix:"ms", label:"median orchestration overhead", sub:"excl. model + tool time" },
    { end:99.98, decimals:2, suffix:"%", label:"control-plane uptime", sub:"trailing 90 days" },
    { end:6, label:"tool execution modes", sub:"one contract, any runtime" },
  ],
  logos: ["Marine Operations","Finance Workflow","Procurement","Customer Service","Vessel Maintenance","Fleet Intelligence"],
};

Object.assign(window, {
  Icon, ICON_PATHS, ThemeCtx, useTheme, useThemeState, useReveal, Reveal, useInViewOnce,
  Btn, CopyChip, SectionHead, CountUp, MAACC,
});


/* ============================================================
   MAACC Landing — animated node-field canvas background
   Drifting nodes + proximity links that brighten near cursor.
   Theme-aware, pauses offscreen, respects reduced-motion.
   ============================================================ */
function NodeField({ density = 1, speed = 1, style = {}, interactive = true }) {
  const canvasRef = useRef(null);
  const wrapRef = useRef(null);

  useEffect(() => {
    const canvas = canvasRef.current, wrap = wrapRef.current;
    if (!canvas || !wrap) return;
    const ctx = canvas.getContext("2d");
    const reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    let w = 0, h = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
    let nodes = [];
    let raf = 0, running = true, visible = true;
    const mouse = { x: -9999, y: -9999, active: false };

    const palette = () => {
      const dark = document.documentElement.getAttribute("data-theme") === "dark";
      return dark
        ? { node:"rgba(176,124,216,0.9)", node2:"rgba(76,208,189,0.85)", line:"140,110,200", hot:"124,211,197", dot:"rgba(150,180,240,0.5)" }
        : { node:"rgba(92,15,140,0.75)", node2:"rgba(15,155,131,0.7)", line:"92,15,140", hot:"15,155,131", dot:"rgba(20,27,43,0.4)" };
    };
    let col = palette();

    function resize() {
      const r = wrap.getBoundingClientRect();
      w = r.width; h = r.height;
      canvas.width = w * dpr; canvas.height = h * dpr;
      canvas.style.width = w + "px"; canvas.style.height = h + "px";
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      const target = Math.round((w * h) / 20000 * density);
      const count = Math.max(14, Math.min(90, target));
      nodes = new Array(count).fill(0).map(() => ({
        x: Math.random() * w, y: Math.random() * h,
        vx: (Math.random() - 0.5) * 0.28 * speed, vy: (Math.random() - 0.5) * 0.28 * speed,
        r: Math.random() * 1.6 + 1.1,
        hue: Math.random() > 0.68 ? 1 : 0,
      }));
    }

    function step() {
      ctx.clearRect(0, 0, w, h);
      const LINK = 132;
      for (let i = 0; i < nodes.length; i++) {
        const n = nodes[i];
        if (!reduce) { n.x += n.vx; n.y += n.vy; }
        if (n.x < 0 || n.x > w) n.vx *= -1;
        if (n.y < 0 || n.y > h) n.vy *= -1;
        n.x = Math.max(0, Math.min(w, n.x));
        n.y = Math.max(0, Math.min(h, n.y));
      }
      // links
      for (let i = 0; i < nodes.length; i++) {
        for (let j = i + 1; j < nodes.length; j++) {
          const a = nodes[i], b = nodes[j];
          const dx = a.x - b.x, dy = a.y - b.y;
          const d2 = dx * dx + dy * dy;
          if (d2 < LINK * LINK) {
            const d = Math.sqrt(d2);
            let alpha = (1 - d / LINK) * 0.5;
            let c = col.line;
            if (mouse.active) {
              const mx = (a.x + b.x) / 2 - mouse.x, my = (a.y + b.y) / 2 - mouse.y;
              const md = Math.sqrt(mx * mx + my * my);
              if (md < 150) { const boost = 1 - md / 150; alpha += boost * 0.55; c = col.hot; }
            }
            ctx.strokeStyle = `rgba(${c},${alpha})`;
            ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
          }
        }
      }
      // nodes
      for (let i = 0; i < nodes.length; i++) {
        const n = nodes[i];
        let r = n.r, glow = 0;
        if (mouse.active) {
          const dx = n.x - mouse.x, dy = n.y - mouse.y;
          const md = Math.sqrt(dx * dx + dy * dy);
          if (md < 150) glow = 1 - md / 150;
        }
        ctx.beginPath();
        ctx.arc(n.x, n.y, r + glow * 1.6, 0, Math.PI * 2);
        ctx.fillStyle = n.hue ? col.node2 : col.node;
        ctx.globalAlpha = 0.65 + glow * 0.35;
        ctx.fill();
        ctx.globalAlpha = 1;
      }
      if (running && visible && !reduce) raf = requestAnimationFrame(step);
    }

    function onMove(e) {
      if (!interactive) return;
      const r = wrap.getBoundingClientRect();
      mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; mouse.active = true;
    }
    function onLeave() { mouse.active = false; mouse.x = -9999; mouse.y = -9999; }

    resize();
    step();
    if (reduce) { /* one static frame drawn already */ }

    const ro = new ResizeObserver(() => { resize(); if (reduce) step(); });
    ro.observe(wrap);
    if (interactive) { window.addEventListener("mousemove", onMove); wrap.addEventListener("mouseleave", onLeave); }

    const io = new IntersectionObserver((es) => {
      es.forEach(e => {
        visible = e.isIntersecting;
        if (visible && !raf && !reduce) { raf = requestAnimationFrame(step); }
        if (!visible && raf) { cancelAnimationFrame(raf); raf = 0; }
      });
    }, { threshold: 0 });
    io.observe(wrap);

    const themeObs = new MutationObserver(() => { col = palette(); if (reduce) step(); });
    themeObs.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });

    const onVis = () => { running = !document.hidden; if (running && visible && !raf && !reduce) raf = requestAnimationFrame(step); };
    document.addEventListener("visibilitychange", onVis);

    return () => {
      running = false; if (raf) cancelAnimationFrame(raf);
      ro.disconnect(); io.disconnect(); themeObs.disconnect();
      window.removeEventListener("mousemove", onMove);
      wrap.removeEventListener("mouseleave", onLeave);
      document.removeEventListener("visibilitychange", onVis);
    };
  }, [density, speed, interactive]);

  return (
    <div ref={wrapRef} style={{ position: "absolute", inset: 0, overflow: "hidden", ...style }} aria-hidden="true">
      <canvas ref={canvasRef} style={{ position: "absolute", inset: 0 }} />
    </div>
  );
}

window.NodeField = NodeField;


/* ============================================================
   MAACC Landing — top navigation
   ============================================================ */
function LogoMark({ size = 34 }) {
  return (
    <div style={{
      width:size, height:size, borderRadius:size*0.27, flexShrink:0, position:"relative", overflow:"hidden",
      background:"linear-gradient(145deg, var(--purple-500), var(--navy-900))",
      display:"flex", alignItems:"center", justifyContent:"center",
      boxShadow:"0 2px 10px rgba(60,10,90,.4), inset 0 1px 0 rgba(255,255,255,.16)" }}>
      <svg width={size*0.62} height={size*0.62} viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="7.5" stroke="#fff" strokeWidth="1.5" fill="none" />
        <circle cx="12" cy="4.5" r="1.85" fill="#fff" />
        <circle cx="5.5" cy="15.75" r="1.85" fill="#fff" />
        <circle cx="18.5" cy="15.75" r="1.85" fill="#fff" />
        <circle cx="12" cy="12" r="1.95" fill="var(--orange-500)" />
      </svg>
    </div>
  );
}
function Wordmark({ onNav }) {
  return (
    <a href="#top" onClick={onNav} style={{ display:"flex", alignItems:"center", gap:11 }}>
      <LogoMark />
      <div style={{ lineHeight:1 }}>
        <div style={{ fontSize:17, fontWeight:700, letterSpacing:.4, color:"var(--text)" }}>MAACC</div>
        <div style={{ fontSize:8.5, fontWeight:600, color:"var(--text-3)", letterSpacing:1.3, marginTop:3, textTransform:"uppercase" }}>Agent Control Centre</div>
      </div>
    </a>
  );
}

const NAV_LINKS = [
  { label:"Platform", href:"#platform" },
  { label:"How it works", href:"#how" },
  { label:"Docs", href:"#docs" },
  { label:"Pricing", href:"#pricing" },
  { label:"Onboarding", href:"#onboarding" },
];

function ThemeToggle({ compact }) {
  const { theme, toggle } = useTheme();
  return (
    <button onClick={toggle} aria-label="Toggle theme" className="ls-glass" style={{
      width:42, height:42, borderRadius:"var(--r-md)", cursor:"pointer", display:"inline-flex",
      alignItems:"center", justifyContent:"center", color:"var(--text-2)", transition:"color .2s, border-color .2s" }}
      onMouseEnter={e => e.currentTarget.style.color = "var(--primary)"}
      onMouseLeave={e => e.currentTarget.style.color = "var(--text-2)"}>
      <Icon name={theme === "dark" ? "sun" : "moon"} size={19} />
    </button>
  );
}

function TopNav() {
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 12);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);
  useEffect(() => {
    document.body.style.overflow = menuOpen ? "hidden" : "";
    return () => { document.body.style.overflow = ""; };
  }, [menuOpen]);

  const closeMenu = () => setMenuOpen(false);

  return (
    <>
      <header style={{
        position:"fixed", top:0, left:0, right:0, zIndex:100, transition:"all .3s ease",
        background: scrolled ? "var(--nav-bg)" : "transparent",
        backdropFilter: scrolled ? "blur(16px) saturate(1.4)" : "none",
        WebkitBackdropFilter: scrolled ? "blur(16px) saturate(1.4)" : "none",
        borderBottom: scrolled ? "1px solid var(--glass-brd)" : "1px solid transparent" }}>
        <div className="ls-wrap" style={{ display:"flex", alignItems:"center", gap:20, height:72 }}>
          <Wordmark onNav={closeMenu} />
          <nav className="nav-desktop" style={{ display:"flex", alignItems:"center", gap:4, marginLeft:14 }}>
            {NAV_LINKS.map(l => (
              <a key={l.href} href={l.href} className="nav-link" style={{
                fontSize:14.5, fontWeight:500, color:"var(--text-2)", padding:"8px 13px", borderRadius:8, transition:"color .16s, background .16s" }}>
                {l.label}
              </a>
            ))}
          </nav>
          <div style={{ flex:1 }} />
          <div className="nav-desktop" style={{ display:"flex", alignItems:"center", gap:11 }}>
            <ThemeToggle />
            <a href="/login" className="nav-link" style={{ fontSize:14.5, fontWeight:600, color:"var(--text)", padding:"8px 6px" }}>Log in</a>
            <Btn href="/register" size="sm" icon="arrowRight" style={{ flexDirection:"row-reverse" }}>Start free</Btn>
          </div>
          <button className="nav-mobile-btn" onClick={() => setMenuOpen(o => !o)} aria-label="Menu" style={{
            display:"none", width:42, height:42, borderRadius:10, border:"1px solid var(--glass-brd)", background:"var(--glass)",
            color:"var(--text)", cursor:"pointer", alignItems:"center", justifyContent:"center" }}>
            <Icon name={menuOpen ? "x" : "menu"} size={22} />
          </button>
        </div>
      </header>

      {/* mobile drawer */}
      <div className="nav-drawer" style={{
        position:"fixed", inset:0, zIndex:99, display: menuOpen ? "flex" : "none", flexDirection:"column",
        padding:"92px 26px 30px", background:"var(--nav-bg)", backdropFilter:"blur(20px)", WebkitBackdropFilter:"blur(20px)" }}>
        <div style={{ display:"flex", flexDirection:"column", gap:4 }}>
          {NAV_LINKS.map(l => (
            <a key={l.href} href={l.href} onClick={closeMenu} style={{
              fontSize:22, fontWeight:600, color:"var(--text)", padding:"14px 6px", borderBottom:"1px solid var(--border)" }}>{l.label}</a>
          ))}
        </div>
        <div style={{ display:"flex", gap:12, marginTop:26, alignItems:"center" }}>
          <Btn href="/register" icon="arrowRight" style={{ flex:1, flexDirection:"row-reverse" }}>Start free</Btn>
          <Btn href="/login" variant="ghost" style={{ flex:1 }}>Log in</Btn>
          <ThemeToggle />
        </div>
      </div>
    </>
  );
}

window.TopNav = TopNav;
window.LogoMark = LogoMark;
window.Wordmark = Wordmark;


/* ============================================================
   MAACC Landing — Hero
   ============================================================ */
function HeroConsole() {
  // looping micro-demo: top run steps through the lifecycle, numbers tick
  const STEPS = [
    { k:"running",  label:"running",           cls:"st-running", pct:22 },
    { k:"tool",     label:"requires_tool",     cls:"st-tool",    pct:48 },
    { k:"wait",     label:"waiting_for_client",cls:"st-wait",    pct:70 },
    { k:"done",     label:"completed",         cls:"st-done",    pct:100 },
  ];
  const [si, setSi] = useState(0);
  const [runs, setRuns] = useState(1240);
  const [ms, setMs] = useState(312);
  const reduce = useRef(false);
  useEffect(() => {
    reduce.current = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduce.current) { setSi(3); return; }
    const t = setInterval(() => setSi(s => (s + 1) % STEPS.length), 1500);
    const t2 = setInterval(() => { setRuns(r => r + Math.floor(Math.random() * 3)); setMs(() => 280 + Math.floor(Math.random() * 70)); }, 1500);
    return () => { clearInterval(t); clearInterval(t2); };
  }, []);
  const step = STEPS[si];

  const feed = [
    { agent:"Operations Summary", app:"Marine Ops", model:"GPT-4o" },
    { agent:"Approval Review", app:"Finance", model:"Claude 3.7" },
    { agent:"Customer Trend", app:"Customer Svc", model:"GPT-4o" },
  ];
  const feedStatus = ["done","done"];

  return (
    <div className="ls-glass" style={{
      borderRadius:"var(--r-2xl)", boxShadow:"var(--sh-lg)", overflow:"hidden", position:"relative",
      animation:"floaty 7s ease-in-out infinite" }}>
      {/* window bar */}
      <div style={{ display:"flex", alignItems:"center", gap:10, padding:"13px 16px", borderBottom:"1px solid var(--glass-brd)" }}>
        <div style={{ display:"flex", gap:7 }}>
          {["#ff5f57","#febc2e","#28c840"].map(c => <span key={c} style={{ width:11, height:11, borderRadius:11, background:c, opacity:.9 }} />)}
        </div>
        <div style={{ display:"flex", alignItems:"center", gap:8, marginLeft:8 }}>
          <LogoMark size={20} />
          <span style={{ fontSize:12.5, fontWeight:600, color:"var(--text-2)" }}>Control Centre</span>
        </div>
        <div style={{ flex:1 }} />
        <span className="ls-chip" style={{ height:22, fontSize:11, color:"var(--primary)", background:"var(--primary-soft)", borderColor:"var(--primary-soft-2)" }}>
          <span style={{ width:6, height:6, borderRadius:6, background:"var(--primary)", animation:"pulseDot 1.6s infinite" }} /> Production
        </span>
      </div>

      <div style={{ padding:16, display:"grid", gap:14 }}>
        {/* stat tiles */}
        <div style={{ display:"grid", gridTemplateColumns:"repeat(3,1fr)", gap:10 }}>
          {[
            { icon:"agents", label:"Active agents", val:"18", tone:"var(--primary)" },
            { icon:"activity", label:"Runs today", val:runs.toLocaleString(), tone:"var(--teal-500)" },
            { icon:"gauge", label:"Median latency", val:ms + "ms", tone:"var(--orange-500)" },
          ].map((t, i) => (
            <div key={i} style={{ background:"var(--surface)", border:"1px solid var(--border)", borderRadius:12, padding:"11px 12px" }}>
              <div style={{ display:"flex", alignItems:"center", gap:6, color:t.tone }}><Icon name={t.icon} size={14} />
                <span style={{ fontSize:10.5, fontWeight:600, color:"var(--text-3)", textTransform:"uppercase", letterSpacing:.4 }}>{t.label}</span></div>
              <div className="tnum" style={{ fontSize:22, fontWeight:700, marginTop:5, letterSpacing:-.5 }}>{t.val}</div>
            </div>
          ))}
        </div>

        {/* live run */}
        <div style={{ background:"var(--surface)", border:"1px solid var(--border)", borderRadius:12, overflow:"hidden" }}>
          <div style={{ padding:"11px 13px 12px", borderBottom:"1px solid var(--border)" }}>
            <div style={{ display:"flex", alignItems:"center", justifyContent:"space-between" }}>
              <div style={{ display:"flex", alignItems:"center", gap:8 }}>
                <span style={{ width:26, height:26, borderRadius:7, background:"var(--primary-soft)", color:"var(--primary)", display:"flex", alignItems:"center", justifyContent:"center" }}><Icon name="agents" size={15} /></span>
                <div>
                  <div style={{ fontSize:12.5, fontWeight:700 }}>Operations Summary Agent</div>
                  <div className="mono" style={{ fontSize:10.5, color:"var(--text-3)" }}>run_8f3c · GPT-4o</div>
                </div>
              </div>
              <span className={step.cls} style={{ fontSize:11.5, fontWeight:700, fontFamily:"var(--mono)", display:"flex", alignItems:"center", gap:6, transition:"color .3s" }}>
                <span style={{ width:7, height:7, borderRadius:7, background:"currentColor", animation: step.k === "done" ? "none" : "pulseDot 1.2s infinite" }} />{step.label}
              </span>
            </div>
            <div style={{ height:5, borderRadius:5, background:"var(--surface-3)", marginTop:11, overflow:"hidden" }}>
              <div style={{ height:"100%", width:step.pct + "%", borderRadius:5, transition:"width .6s cubic-bezier(.4,0,.2,1)",
                background: step.k === "done" ? "var(--teal-500)" : step.k === "tool" || step.k === "wait" ? "var(--orange-500)" : "var(--primary)" }} />
            </div>
            {(step.k === "tool" || step.k === "wait") && (
              <div style={{ marginTop:10, display:"flex", alignItems:"center", gap:8, fontSize:11, color:"var(--orange-500)", fontFamily:"var(--mono)", animation:"popIn .3s ease" }}>
                <Icon name="link" size={13} /> client-side tool → getOperationalRecords() runs in your app
              </div>
            )}
          </div>
          {feed.map((f, i) => (
            <div key={i} style={{ display:"flex", alignItems:"center", gap:9, padding:"9px 13px", borderTop: i ? "1px solid var(--border)" : "none" }}>
              <span className="st-done" style={{ width:7, height:7, borderRadius:7, background:"currentColor", flexShrink:0 }} />
              <span style={{ fontSize:12, fontWeight:600, flex:1, whiteSpace:"nowrap", overflow:"hidden", textOverflow:"ellipsis" }}>{f.agent}</span>
              <span className="mono" style={{ fontSize:10.5, color:"var(--text-3)" }}>{f.app}</span>
              <span className="ls-chip" style={{ height:20, fontSize:10, padding:"0 8px" }}>{f.model}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function Hero() {
  return (
    <section id="top" style={{ position:"relative", paddingTop:150, paddingBottom:90, overflow:"hidden" }}>
      <div style={{ position:"absolute", inset:0, background:"linear-gradient(180deg, var(--hero-1), var(--hero-2) 55%, var(--bg))" }} />
      <div className="ls-gridbg" style={{ opacity:.9 }} />
      <NodeField density={1} speed={1} />
      <div className="ls-glow" style={{ width:520, height:520, top:-140, left:"52%", background:"var(--glow-a)", animation:"glowPulse 9s ease-in-out infinite" }} />
      <div className="ls-glow" style={{ width:420, height:420, top:120, left:"-8%", background:"var(--glow-b)", animation:"glowPulse 11s ease-in-out infinite" }} />
      <div className="ls-glow" style={{ width:360, height:360, top:40, right:"-6%", background:"var(--glow-c)", animation:"glowPulse 13s ease-in-out infinite" }} />

      <div className="ls-wrap" style={{ position:"relative", zIndex:2 }}>
        <div style={{ maxWidth:880, margin:"0 auto", textAlign:"center" }}>
          <Reveal as="div">
            <span className="ls-eyebrow"><span className="dot" /> Multi AI Agent Control Centre</span>
          </Reveal>
          <Reveal as="h1" delay={1} style={{
            fontSize:"clamp(40px,7vw,80px)", fontWeight:700, lineHeight:1.02, letterSpacing:"-2.6px",
            margin:"22px 0 0", textWrap:"balance" }}>
            One <span className="ls-grad">control centre</span> for every AI agent.
          </Reveal>
          <Reveal as="p" delay={2} style={{ fontSize:"clamp(16px,1.8vw,20px)", color:"var(--text-2)", lineHeight:1.6, margin:"22px auto 0", maxWidth:640 }}>
            Create, govern and ship AI agents from a single platform. MAACC orchestrates models, tools and runs behind one secure API —
            while your data and business logic never leave your app.
          </Reveal>
          <Reveal as="div" delay={3} style={{ display:"flex", gap:13, justifyContent:"center", flexWrap:"wrap", marginTop:30 }}>
            <Btn href="/register" icon="arrowRight" style={{ flexDirection:"row-reverse" }}>Start building free</Btn>
            <Btn href="#docs" variant="glass" icon="book">Read the docs</Btn>
          </Reveal>
          <Reveal as="div" delay={4} style={{ display:"flex", justifyContent:"center", marginTop:20 }}>
            <CopyChip text="npm i @maacc/sdk" />
          </Reveal>
          <Reveal as="div" delay={5} style={{ marginTop:40 }}>
            <div className="ls-kicker" style={{ fontSize:11, marginBottom:13 }}>Orchestrates your approved model catalog</div>
            <div style={{ display:"flex", gap:9, justifyContent:"center", flexWrap:"wrap" }}>
              {MAACC.llms.slice(0,5).map(m => (
                <span key={m.name} className="ls-chip" style={{ height:30 }}>
                  <span style={{ width:7, height:7, borderRadius:7, background:m.tone }} />{m.name}
                </span>
              ))}
            </div>
          </Reveal>
        </div>

        <Reveal as="div" delay={4} style={{ maxWidth:760, margin:"56px auto 0" }}>
          <HeroConsole />
        </Reveal>
      </div>
      {/* scroll cue */}
      <div style={{ position:"relative", zIndex:2, display:"flex", justifyContent:"center", marginTop:44 }}>
        <a href="#platform" aria-label="Scroll" style={{ color:"var(--text-3)", display:"inline-flex", animation:"floaty 2.4s ease-in-out infinite" }}>
          <Icon name="arrowDown" size={22} />
        </a>
      </div>
    </section>
  );
}

window.Hero = Hero;
window.HeroConsole = HeroConsole;


/* ============================================================
   MAACC Landing — CENTERPIECE 1: animated architecture diagram
   Your App  <->  MAACC Control Plane  <->  Model providers
   with a highlighted client-side tool round-trip.
   ============================================================ */
function useMediaQuery(q) {
  const [m, setM] = useState(() => (typeof window !== "undefined" && window.matchMedia ? window.matchMedia(q).matches : false));
  useEffect(() => {
    const mq = window.matchMedia(q);
    const on = () => setM(mq.matches);
    on(); mq.addEventListener("change", on);
    return () => mq.removeEventListener("change", on);
  }, [q]);
  return m;
}
window.useMediaQuery = useMediaQuery;

const FLOW_STEPS = [
  { id:"invoke", n:"01", label:"Invoke", color:"var(--primary)", paths:["A"],
    desc:"Your app calls an agent through the MAACC SDK or REST API — one endpoint, any language." },
  { id:"orchestrate", n:"02", label:"Orchestrate", color:"var(--teal-500)", paths:["B","C","D"],
    desc:"MAACC runs the agent: selects the approved model, plans tool calls, and enforces every policy." },
  { id:"tool", n:"03", label:"Client-side tool", color:"var(--orange-500)", paths:["E"],
    desc:"Needs your data? MAACC pauses the run and returns a typed tool request back to your SDK." },
  { id:"resume", n:"04", label:"Resume & respond", color:"var(--teal-500)", paths:["F","A"],
    desc:"Your app runs the tool locally against its own DB, returns the result, MAACC resumes and answers." },
];

/* path geometry in a 980x540 space */
const PATHS = {
  A: "M250,222 H366",
  B: "M614,168 H726",
  C: "M726,200 H614",
  D: "M614,360 H726",
  E: "M366,318 C320,318 300,318 250,318",
  F: "M250,352 H366",
};
const PATH_META = {
  A:{ color:"var(--primary)", dir:1 },
  B:{ color:"var(--teal-500)", dir:1 },
  C:{ color:"var(--text-3)", dir:1 },
  D:{ color:"var(--teal-500)", dir:1 },
  E:{ color:"var(--orange-500)", dir:1 },
  F:{ color:"var(--teal-500)", dir:1 },
};

function DiagramStage({ active, setActive }) {
  const activePaths = new Set(FLOW_STEPS[active].paths);
  const nodeCard = (x, y, w, h, extra = {}) => ({
    position:"absolute", left:`${x/980*100}%`, top:`${y/540*100}%`, width:`${w/980*100}%`, height:`${h/540*100}%`,
    ...extra,
  });

  return (
    <div style={{ position:"relative", width:"100%", maxWidth:980, margin:"0 auto" }}>
      <div style={{ position:"relative", width:"100%", aspectRatio:"980 / 540" }}>
        {/* connectors */}
        <svg viewBox="0 0 980 540" style={{ position:"absolute", inset:0, width:"100%", height:"100%", overflow:"visible" }}>
          <defs>
            {Object.keys(PATHS).map(k => (
              <marker key={k} id={`ar--${k}`} markerWidth="9" markerHeight="9" refX="6" refY="3" orient="auto">
                <path d="M0,0 L6,3 L0,6 Z" fill={PATH_META[k].color} opacity={activePaths.has(k) ? 1 : .5} />
              </marker>
            ))}
          </defs>
          {Object.entries(PATHS).map(([k, d]) => {
            const on = activePaths.has(k);
            return (
              <g key={k}>
                {/* base line */}
                <path d={d} id={`p-${k}`} fill="none" stroke={PATH_META[k].color}
                  strokeWidth={on ? 2.4 : 1.4} strokeOpacity={on ? .95 : .32}
                  strokeDasharray="7 7" markerEnd={`url(#ar--${k})`}
                  style={{ animation: `dashFlow ${on ? 1 : 2.4}s linear infinite`, transition:"stroke-width .3s, stroke-opacity .3s" }} />
                {/* traveling packet */}
                <circle r={on ? 4.5 : 3} fill={PATH_META[k].color} opacity={on ? 1 : .55}>
                  <animateMotion dur={`${on ? 1.5 : 2.8}s`} repeatCount="indefinite" rotate="auto">
                    <mpath href={`#p-${k}`} xlinkHref={`#p-${k}`} />
                  </animateMotion>
                </circle>
              </g>
            );
          })}
        </svg>

        {/* ---- Your Application (left) ---- */}
        <div className="ls-card" style={{ ...nodeCard(30, 150, 220, 240), padding:14, display:"flex", flexDirection:"column",
          borderColor: activePaths.has("E") || activePaths.has("F") ? "var(--orange-500)" : "var(--border)",
          transition:"border-color .3s", boxShadow: activePaths.has("E") ? "var(--sh-glow)" : "var(--sh-sm)" }}>
          <div style={{ display:"flex", alignItems:"center", gap:8 }}>
            <span style={{ width:30, height:30, borderRadius:8, background:"var(--surface-3)", color:"var(--orange-500)", display:"flex", alignItems:"center", justifyContent:"center" }}><Icon name="stack" size={17} /></span>
            <div style={{ fontSize:13.5, fontWeight:700 }}>Your Application</div>
          </div>
          <span className="ls-chip" style={{ marginTop:10, height:22, fontSize:10.5, alignSelf:"flex-start" }}><Icon name="sdk" size={12} /> MAACC SDK</span>
          <div style={{ marginTop:12, display:"grid", gap:7 }}>
            {[["database","Your database"],["cpu","Business logic"],["fingerprint","User permissions"]].map(([ic,t]) => (
              <div key={t} style={{ display:"flex", alignItems:"center", gap:8, fontSize:11.5, color:"var(--text-2)" }}>
                <Icon name={ic} size={13} style={{ color:"var(--text-3)" }} />{t}
              </div>
            ))}
          </div>
          <div style={{ flex:1 }} />
          <div style={{ display:"flex", alignItems:"center", gap:6, fontSize:10.5, fontWeight:600, color:"var(--teal-500)", background:"var(--teal-100)", padding:"6px 8px", borderRadius:7 }}>
            <Icon name="lock" size={12} /> Data never leaves
          </div>
        </div>

        {/* ---- MAACC control plane (center) ---- */}
        <div className="ls-card ls-card-glow" style={{ ...nodeCard(366, 90, 248, 360), padding:14, display:"flex", flexDirection:"column",
          background:"var(--surface)", borderColor:"var(--primary-soft-2)", boxShadow:"var(--sh-glow)" }}>
          <div style={{ display:"flex", alignItems:"center", gap:9 }}>
            <LogoMark size={28} />
            <div>
              <div style={{ fontSize:13.5, fontWeight:700 }}>MAACC Control Plane</div>
              <div style={{ fontSize:10, color:"var(--text-3)", fontWeight:600, letterSpacing:.3 }}>orchestrate · govern · observe</div>
            </div>
          </div>
          <div style={{ marginTop:12, display:"grid", gap:8, flex:1 }}>
            {[
              { ic:"agents", t:"Agent Runtime", s:"prompt · plan · resume", tone:"var(--primary)", hot:activePaths.has("A") },
              { ic:"llm", t:"LLM Gateway", s:"approved model routing", tone:"var(--teal-500)", hot:activePaths.has("B")||activePaths.has("C") },
              { ic:"tools", t:"Tool Orchestrator", s:"contracts · 6 exec modes", tone:"var(--orange-500)", hot:activePaths.has("E")||activePaths.has("F")||activePaths.has("D") },
              { ic:"shieldCheck", t:"Governance & Audit", s:"RBAC · policy · traces", tone:"var(--blue-400)", hot:false },
            ].map(r => (
              <div key={r.t} style={{ display:"flex", alignItems:"center", gap:9, padding:"9px 10px", borderRadius:9,
                background: r.hot ? "var(--surface-3)" : "var(--surface-2)", border:`1px solid ${r.hot ? r.tone : "var(--border)"}`,
                transition:"border-color .3s, background .3s" }}>
                <span style={{ width:26, height:26, borderRadius:7, background:"var(--surface)", color:r.tone, display:"flex", alignItems:"center", justifyContent:"center", flexShrink:0 }}><Icon name={r.ic} size={15} /></span>
                <div style={{ minWidth:0 }}>
                  <div style={{ fontSize:12, fontWeight:700, lineHeight:1.2 }}>{r.t}</div>
                  <div className="mono" style={{ fontSize:9.5, color:"var(--text-3)" }}>{r.s}</div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* ---- providers (right top) ---- */}
        <div className="ls-card" style={{ ...nodeCard(730, 108, 216, 150), padding:13,
          borderColor: activePaths.has("B") ? "var(--teal-500)" : "var(--border)", transition:"border-color .3s" }}>
          <div style={{ display:"flex", alignItems:"center", gap:8 }}>
            <span style={{ width:28, height:28, borderRadius:7, background:"var(--surface-3)", color:"var(--teal-500)", display:"flex", alignItems:"center", justifyContent:"center" }}><Icon name="cpu" size={16} /></span>
            <div style={{ fontSize:12.5, fontWeight:700 }}>Model providers</div>
          </div>
          <div style={{ display:"flex", flexWrap:"wrap", gap:6, marginTop:11 }}>
            {["Azure","Bedrock","Vertex","On-Prem"].map(p => (
              <span key={p} className="ls-chip" style={{ height:22, fontSize:10.5 }}>{p}</span>
            ))}
          </div>
        </div>

        {/* ---- hosted tools (right bottom) ---- */}
        <div className="ls-card" style={{ ...nodeCard(730, 300, 216, 150), padding:13,
          borderColor: activePaths.has("D") ? "var(--teal-500)" : "var(--border)", transition:"border-color .3s" }}>
          <div style={{ display:"flex", alignItems:"center", gap:8 }}>
            <span style={{ width:28, height:28, borderRadius:7, background:"var(--surface-3)", color:"var(--primary)", display:"flex", alignItems:"center", justifyContent:"center" }}><Icon name="book" size={16} /></span>
            <div style={{ fontSize:12.5, fontWeight:700 }}>Hosted tools</div>
          </div>
          <div style={{ display:"grid", gap:6, marginTop:10 }}>
            {[["globe","Web search"],["book","Knowledge base"],["doc","Summarize"]].map(([ic,t]) => (
              <div key={t} style={{ display:"flex", alignItems:"center", gap:7, fontSize:11, color:"var(--text-2)" }}><Icon name={ic} size={12} style={{ color:"var(--text-3)" }} />{t}</div>
            ))}
          </div>
        </div>

        {/* floating labels near key channels */}
        <FlowTag x={308} y={196} on={activePaths.has("A")} color="var(--primary)" text="invoke agent" />
        <FlowTag x={308} y={300} on={activePaths.has("E")} color="var(--orange-500)" text="tool request · pause" />
        <FlowTag x={308} y={372} on={activePaths.has("F")} color="var(--teal-500)" text="tool result · resume" />
        <FlowTag x={672} y={146} on={activePaths.has("B")} color="var(--teal-500)" text="model call" />
      </div>
    </div>
  );
}

function FlowTag({ x, y, on, color, text }) {
  return (
    <div style={{ position:"absolute", left:`${x/980*100}%`, top:`${y/540*100}%`, transform:"translate(-50%,-50%)",
      pointerEvents:"none", transition:"opacity .3s", opacity: on ? 1 : 0 }}>
      <span className="mono" style={{ fontSize:10.5, fontWeight:600, color, background:"var(--surface)", border:`1px solid ${color}`,
        padding:"2px 8px", borderRadius:6, whiteSpace:"nowrap", boxShadow:"var(--sh-sm)" }}>{text}</span>
    </div>
  );
}

function DiagramMobile({ active, setActive }) {
  const zones = [
    { ic:"stack", t:"Your Application", s:"SDK · your DB · business logic · permissions", tone:"var(--orange-500)" },
    { ic:"agents", t:"MAACC Control Plane", s:"runtime · LLM gateway · tools · governance", tone:"var(--primary)" },
    { ic:"cpu", t:"Model providers & hosted tools", s:"Azure · Bedrock · Vertex · on-prem · search", tone:"var(--teal-500)" },
  ];
  return (
    <div style={{ maxWidth:440, margin:"0 auto", display:"grid", gap:12 }}>
      {zones.map((z, i) => (
        <React.Fragment key={z.t}>
          <div className="ls-card" style={{ padding:14, display:"flex", alignItems:"center", gap:11, borderColor: i===1 ? "var(--primary-soft-2)" : "var(--border)", boxShadow: i===1 ? "var(--sh-glow)" : "var(--sh-sm)" }}>
            <span style={{ width:38, height:38, borderRadius:9, background:"var(--surface-3)", color:z.tone, display:"flex", alignItems:"center", justifyContent:"center", flexShrink:0 }}><Icon name={z.ic} size={20} /></span>
            <div><div style={{ fontSize:14, fontWeight:700 }}>{z.t}</div><div className="mono" style={{ fontSize:11, color:"var(--text-3)" }}>{z.s}</div></div>
          </div>
          {i < zones.length - 1 && <div style={{ display:"flex", justifyContent:"center", color:"var(--text-3)" }}><Icon name="arrowDown" size={18} /></div>}
        </React.Fragment>
      ))}
    </div>
  );
}

function Architecture() {
  const isDesktop = useMediaQuery("(min-width: 860px)");
  const [active, setActive] = useState(0);
  const paused = useRef(false);
  const ref = useInViewOnce(() => {}, 0.2);
  useEffect(() => {
    const reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduce) return;
    const t = setInterval(() => { if (!paused.current) setActive(a => (a + 1) % FLOW_STEPS.length); }, 2800);
    return () => clearInterval(t);
  }, []);

  return (
    <section id="platform" className="ls-section" style={{ position:"relative" }}>
      <div className="ls-wrap">
        <SectionHead center kicker="The orchestration story"
          title="One platform between your apps and every model"
          sub="MAACC sits in the middle: it runs agents, routes to approved models, and calls tools — while your data and business logic stay behind your own walls." />

        <Reveal as="div" style={{ marginTop:54 }}>
          <div ref={ref}>
            {isDesktop ? <DiagramStage active={active} setActive={setActive} /> : <DiagramMobile active={active} setActive={setActive} />}
          </div>
        </Reveal>

        {/* interactive stepper */}
        <Reveal as="div" delay={2} style={{ marginTop:38, display:"grid", gridTemplateColumns:"repeat(auto-fit,minmax(210px,1fr))", gap:12 }}>
          {FLOW_STEPS.map((s, i) => {
            const on = i === active;
            return (
              <button key={s.id}
                onMouseEnter={() => { paused.current = true; setActive(i); }}
                onMouseLeave={() => { paused.current = false; }}
                onClick={() => setActive(i)}
                style={{ textAlign:"left", cursor:"pointer", padding:"15px 16px", borderRadius:"var(--r-lg)",
                  background: on ? "var(--surface)" : "var(--surface-2)", border:`1px solid ${on ? s.color : "var(--border)"}`,
                  boxShadow: on ? "var(--sh-md)" : "none", transition:"all .25s", position:"relative", overflow:"hidden" }}>
                <div style={{ position:"absolute", left:0, top:0, bottom:0, width:3, background:s.color, opacity:on?1:0, transition:"opacity .25s" }} />
                <div style={{ display:"flex", alignItems:"center", gap:9 }}>
                  <span className="mono" style={{ fontSize:12, fontWeight:700, color:s.color }}>{s.n}</span>
                  <span style={{ fontSize:14, fontWeight:700 }}>{s.label}</span>
                </div>
                <p style={{ margin:"8px 0 0", fontSize:12.5, color:"var(--text-2)", lineHeight:1.5 }}>{s.desc}</p>
              </button>
            );
          })}
        </Reveal>
      </div>
    </section>
  );
}

window.Architecture = Architecture;


/* ============================================================
   MAACC Landing — CENTERPIECE 2: live agent-run simulator
   Watch a run pause on a client-side tool, execute locally,
   then resume — the pause-and-resume pattern, live.
   ============================================================ */
const RUN_STATUS_META = {
  idle:            { cls:"st-queued", label:"idle" },
  queued:          { cls:"st-queued", label:"queued" },
  running:         { cls:"st-running", label:"running" },
  requires_tool:   { cls:"st-tool", label:"requires_tool" },
  waiting_for_client:{ cls:"st-wait", label:"waiting_for_client" },
  completed:       { cls:"st-done", label:"completed" },
};

function TraceRow({ ev }) {
  return (
    <div style={{ display:"flex", gap:11, animation:"popIn .35s ease" }}>
      <div style={{ display:"flex", flexDirection:"column", alignItems:"center", flexShrink:0 }}>
        <span style={{ width:22, height:22, borderRadius:22, background:"var(--surface)", border:`1.5px solid ${ev.tone}`, color:ev.tone, display:"flex", alignItems:"center", justifyContent:"center" }}>
          <Icon name={ev.icon} size={12} strokeWidth={2.2} />
        </span>
        <span style={{ flex:1, width:1.5, background:"var(--border)", marginTop:3, minHeight:8 }} />
      </div>
      <div style={{ paddingBottom:14, minWidth:0 }}>
        <div style={{ fontSize:12.5, fontWeight:600, color:"var(--text)" }}>{ev.title}</div>
        {ev.detail && <div className="mono" style={{ fontSize:11, color:ev.tone, marginTop:3, wordBreak:"break-word" }}>{ev.detail}</div>}
        {ev.note && <div style={{ fontSize:11, color:"var(--text-3)", marginTop:3 }}>{ev.note}</div>}
      </div>
    </div>
  );
}

function Simulator() {
  const [events, setEvents] = useState([]);
  const [appLog, setAppLog] = useState([]);
  const [status, setStatus] = useState("idle");
  const [progress, setProgress] = useState(0);
  const [answer, setAnswer] = useState("");
  const [playing, setPlaying] = useState(false);
  const [done, setDone] = useState(false);
  const timers = useRef([]);
  const traceRef = useRef(null);

  const clearTimers = () => { timers.current.forEach(clearTimeout); timers.current = []; };
  const at = (ms, fn) => timers.current.push(setTimeout(fn, ms));
  const pushEvent = (ev) => setEvents(list => [...list, ev]);
  const pushLog = (line) => setAppLog(list => [...list, line]);

  useEffect(() => () => clearTimers(), []);
  useEffect(() => { if (traceRef.current) traceRef.current.scrollTop = traceRef.current.scrollHeight; }, [events, answer]);

  const ANSWER = "Across 3 active voyages, 2 exceptions need attention: MV Al Rayyan is running 6h behind at Berth 4, and voyage V-2291 has a documentation gap. Fuel burn is within plan. Recommend reallocating Berth 7 to clear the backlog.";

  function play() {
    clearTimers();
    setEvents([]); setAppLog([]); setAnswer(""); setProgress(0); setDone(false); setPlaying(true); setStatus("queued");

    at(150, () => { setStatus("queued"); setProgress(7);
      pushEvent({ icon:"send", tone:"var(--primary)", title:"Run accepted", detail:"POST /api/agents/ag_ops_summary/runs", note:"from Marine Operations Portal" }); });
    at(850, () => { setStatus("running"); setProgress(20);
      pushEvent({ icon:"llm", tone:"var(--teal-500)", title:"Model selected", detail:"GPT-4o · Azure OpenAI", note:"per project model policy" }); });
    at(1650, () => { setProgress(32);
      pushEvent({ icon:"agents", tone:"var(--primary)", title:"Agent planning", note:"Needs approved operational records to answer" }); });
    at(2500, () => { setStatus("requires_tool"); setProgress(45);
      pushEvent({ icon:"tools", tone:"var(--orange-500)", title:"Tool call requested", detail:"getOperationalRecords({ from:\"2026-01-01\", to:\"2026-03-31\" })", note:"execution mode: client-side" }); });
    at(2900, () => { setStatus("waiting_for_client"); setProgress(52);
      pushEvent({ icon:"pause", tone:"var(--orange-400)", title:"Run paused", note:"Tool request returned to your SDK — MAACC holds the run" }); });

    // app side executes locally
    at(3350, () => pushLog({ tone:"var(--text-2)", t:"◇ SDK received tool request" }));
    at(3750, () => pushLog({ tone:"var(--primary)", t:"▶ handler getOperationalRecords()" }));
    at(4200, () => pushLog({ tone:"var(--teal-500)", t:"✓ user.hasPermission('operations:view')" }));
    at(4750, () => pushLog({ tone:"var(--teal-500)", t:"✓ SELECT … FROM fleet.voyages → 97 rows" }));
    at(5250, () => pushLog({ tone:"var(--orange-500)", t:"↩ return summary  ·  256 KB" }));

    at(5650, () => { setStatus("running"); setProgress(70);
      pushEvent({ icon:"check2", tone:"var(--teal-500)", title:"Tool result received", detail:"validated against output schema", note:"raw rows stay in your app — only the summary returns" }); });
    at(6150, () => { setProgress(84);
      pushEvent({ icon:"agents", tone:"var(--primary)", title:"Agent resumed", note:"composing the final answer" }); });

    // stream answer
    const words = ANSWER.split(" ");
    let base = 6600;
    words.forEach((w, i) => { at(base + i * 55, () => setAnswer(a => (a ? a + " " : "") + w)); });
    const end = base + words.length * 55 + 400;
    at(end, () => { setStatus("completed"); setProgress(100);
      pushEvent({ icon:"checkCircle", tone:"var(--teal-500)", title:"Run completed", detail:"1,380 tokens · $0.021 · 2.4s", note:"traced & auditable in Runs & Audit Logs" }); });
    at(end + 200, () => { setPlaying(false); setDone(true); });
  }

  const inView = useInViewOnce(() => { if (status === "idle") play(); }, 0.35);
  const sm = RUN_STATUS_META[status];

  return (
    <section id="how" className="ls-section" style={{ position:"relative", background:"var(--surface-2)", borderTop:"1px solid var(--border)", borderBottom:"1px solid var(--border)" }}>
      <div className="ls-glow" style={{ width:460, height:460, top:60, right:"-6%", background:"var(--glow-c)", opacity:.5 }} />
      <div className="ls-wrap" style={{ position:"relative" }}>
        <SectionHead center kicker="How it works · pause & resume"
          title="Runs that pause for your code — then pick up where they left off"
          sub="The client-side tool pattern in motion. When an agent needs your data, MAACC pauses, hands the request to your app, and resumes with the result — no database credentials ever shared." />

        <Reveal as="div" delay={1} style={{ marginTop:48 }}>
          <div ref={inView} className="ls-card" style={{ padding:0, overflow:"hidden", boxShadow:"var(--sh-lg)" }}>
            {/* header */}
            <div style={{ display:"flex", alignItems:"center", gap:12, padding:"14px 18px", borderBottom:"1px solid var(--border)", flexWrap:"wrap" }}>
              <div style={{ display:"flex", alignItems:"center", gap:9 }}>
                <span style={{ width:30, height:30, borderRadius:8, background:"var(--primary-soft)", color:"var(--primary)", display:"flex", alignItems:"center", justifyContent:"center" }}><Icon name="agents" size={17} /></span>
                <div>
                  <div style={{ fontSize:13.5, fontWeight:700 }}>Operations Summary Agent</div>
                  <div className="mono" style={{ fontSize:10.5, color:"var(--text-3)" }}>run_8f3c9a · GPT-4o</div>
                </div>
              </div>
              <div style={{ flex:1 }} />
              <span className={sm.cls} style={{ display:"inline-flex", alignItems:"center", gap:7, fontFamily:"var(--mono)", fontSize:12, fontWeight:700 }}>
                <span style={{ width:8, height:8, borderRadius:8, background:"currentColor", animation: playing ? "pulseDot 1.1s infinite" : "none" }} />
                {sm.label}
              </span>
              <Btn size="sm" onClick={play} icon={playing ? "refresh" : done ? "refresh" : "play"} disabled={playing}
                style={{ opacity: playing ? .6 : 1, minWidth:132, flexDirection: (playing||done) ? "row" : "row" }}>
                {playing ? "Running…" : done ? "Replay run" : "Run agent"}
              </Btn>
            </div>
            {/* progress */}
            <div style={{ height:4, background:"var(--surface-3)" }}>
              <div style={{ height:"100%", width:progress+"%", transition:"width .5s cubic-bezier(.4,0,.2,1)",
                background: status==="completed" ? "var(--teal-500)" : (status==="requires_tool"||status==="waiting_for_client") ? "var(--orange-500)" : "var(--primary)" }} />
            </div>

            <div style={{ display:"grid", gridTemplateColumns:"1fr 1.15fr", gap:0 }} className="sim-grid">
              {/* LEFT — your app */}
              <div style={{ padding:18, borderRight:"1px solid var(--border)" }} className="sim-left">
                <div className="ls-kicker" style={{ fontSize:10.5, display:"flex", alignItems:"center", gap:7 }}><Icon name="stack" size={13} style={{ color:"var(--orange-500)" }} /> Your application</div>
                <div style={{ marginTop:12, background:"var(--surface-2)", border:"1px solid var(--border)", borderRadius:10, padding:"12px 13px" }}>
                  <div style={{ fontSize:10.5, fontWeight:600, color:"var(--text-3)", marginBottom:6 }}>USER PROMPT</div>
                  <div style={{ fontSize:13, color:"var(--text)", lineHeight:1.5 }}>Summarise today's fleet operations and flag any exceptions for the duty manager.</div>
                </div>

                <div className="ls-kicker" style={{ fontSize:10.5, marginTop:18, display:"flex", alignItems:"center", gap:7 }}><Icon name="terminal" size={13} style={{ color:"var(--primary)" }} /> Local tool handler</div>
                <div style={{ marginTop:10, background:"var(--code-bg)", border:"1px solid var(--code-border)", borderRadius:10, padding:"12px 13px", minHeight:150 }}>
                  <div className="mono" style={{ fontSize:11, color:"#7fb0ff", lineHeight:1.7 }}>maacc.registerTool(<span className="tok-str">"getOperationalRecords"</span>, fn)</div>
                  <div style={{ marginTop:6, display:"grid", gap:4 }}>
                    {appLog.length === 0 && <div className="mono" style={{ fontSize:11, color:"var(--text-3)" }}>// waiting for tool request…</div>}
                    {appLog.map((l, i) => (
                      <div key={i} className="mono" style={{ fontSize:11.5, color:l.tone, animation:"popIn .3s ease", lineHeight:1.55 }}>{l.t}</div>
                    ))}
                  </div>
                </div>
                <div style={{ marginTop:12, display:"flex", alignItems:"center", gap:8, fontSize:11, color:"var(--text-3)" }}>
                  <Icon name="lock" size={13} style={{ color:"var(--teal-500)" }} /> Runs against your DB, with your permissions. MAACC never connects to it.
                </div>
              </div>

              {/* RIGHT — MAACC trace */}
              <div style={{ padding:18, background:"var(--surface-2)" }} className="sim-right">
                <div className="ls-kicker" style={{ fontSize:10.5, display:"flex", alignItems:"center", gap:7 }}><LogoMark size={16} /> MAACC run trace</div>
                <div ref={traceRef} className="maac-scroll" style={{ marginTop:14, maxHeight:330, overflowY:"auto", paddingRight:4 }}>
                  {events.length === 0 && <div style={{ fontSize:12.5, color:"var(--text-3)", padding:"20px 0" }}>Press <b style={{ color:"var(--text-2)" }}>Run agent</b> to start a live run.</div>}
                  {events.map((ev, i) => <TraceRow key={i} ev={ev} />)}
                  {answer && (
                    <div style={{ marginLeft:33, marginTop:-4, background:"var(--surface)", border:"1px solid var(--border)", borderRadius:"10px", padding:"12px 13px", animation:"popIn .35s ease" }}>
                      <div style={{ display:"flex", alignItems:"center", gap:7, marginBottom:7 }}>
                        <Icon name="sparkles" size={13} style={{ color:"var(--primary)" }} />
                        <span style={{ fontSize:11, fontWeight:700, color:"var(--text-3)", textTransform:"uppercase", letterSpacing:.4 }}>Agent response</span>
                      </div>
                      <div style={{ fontSize:12.5, color:"var(--text)", lineHeight:1.6 }}>{answer}{status !== "completed" && <span className="caret" />}</div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}

window.Simulator = Simulator;


/* ============================================================
   MAACC Landing — CENTERPIECE 3: interactive code playground
   Language + scenario tabs, live syntax highlight, line reveal.
   ============================================================ */
const PG_LANGS = [
  { id:"ts", label:"TypeScript", install:"npm i @maacc/sdk" },
  { id:"py", label:"Python", install:"pip install maacc" },
  { id:"php", label:"PHP", install:"composer require maacc/sdk" },
  { id:"curl", label:"cURL", install:"export MAACC_TOKEN=…" },
];
const PG_SCEN = [
  { id:"run", label:"Run an agent" },
  { id:"tool", label:"Register a client-side tool" },
];

const SNIPPETS = {
  run: {
    ts: `import { MaaccClient } from "@maacc/sdk";

const maacc = new MaaccClient({
  projectId: process.env.MAACC_PROJECT_ID,
  clientId: process.env.MAACC_CLIENT_ID,
  clientSecret: process.env.MAACC_CLIENT_SECRET,
});

// pause-and-resume is handled for you
const res = await maacc.runAgent("operations-summary", {
  input: "Summarise today's fleet operations",
  context: { userId: user.id, department: "Marine" },
});

console.log(res.output);`,
    py: `from maacc import MaaccClient

maacc = MaaccClient(
    project_id=os.environ["MAACC_PROJECT_ID"],
    client_id=os.environ["MAACC_CLIENT_ID"],
    client_secret=os.environ["MAACC_CLIENT_SECRET"],
)

# pause-and-resume is handled for you
res = maacc.run_agent(
    "operations-summary",
    input="Summarise today's fleet operations",
    context={"user_id": user.id},
)

print(res.output)`,
    php: `use Maacc\\Sdk\\MaaccClient;

$maacc = new MaaccClient([
    'project_id'    => env('MAACC_PROJECT_ID'),
    'client_id'     => env('MAACC_CLIENT_ID'),
    'client_secret' => env('MAACC_CLIENT_SECRET'),
]);

// pause-and-resume is handled for you
$res = $maacc->runAgent('operations-summary', [
    'input'   => 'Summarise fleet operations',
    'context' => ['user_id' => $user->id],
]);

echo $res->output;`,
    curl: `# start an agent run
curl -X POST https://api.maacc.dev/v1/agents/operations-summary/runs \\
  -H "Authorization: Bearer $MAACC_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "input": "Summarise fleet operations" }'

# 200 OK
# { "status": "requires_tool", "run_id": "run_8f3c",
#   "tool_call": { "name": "getOperationalRecords" } }`,
  },
  tool: {
    ts: `import { MaaccClient } from "@maacc/sdk";

const maacc = new MaaccClient({ /* credentials */ });

// contract defined in MAACC — you implement it here
maacc.registerTool("getOperationalRecords", async (args, ctx) => {
  if (!ctx.user.can("operations:view")) {
    return { status: "rejected", reason: "not permitted" };
  }
  const data = await db.voyages.summary({
    from: args.from_date,
    to: args.to_date,
  });
  return { summary: data.summary, records: data.rows };
});`,
    py: `from maacc import MaaccClient

maacc = MaaccClient(**credentials)

# contract defined in MAACC — you implement it here
@maacc.tool("getOperationalRecords")
def get_operational_records(args, ctx):
    if not ctx.user.can("operations:view"):
        return {"status": "rejected", "reason": "denied"}
    data = db.voyages.summary(
        from_date=args["from_date"],
        to_date=args["to_date"],
    )
    return {"summary": data.summary, "records": data.rows}`,
    php: `use Maacc\\Sdk\\MaaccClient;

$maacc = new MaaccClient($credentials);

// contract defined in MAACC — you implement it here
$maacc->registerTool('getOperationalRecords', function ($args, $ctx) {
    if (! $ctx->user->can('operations:view')) {
        return ['status' => 'rejected'];
    }
    $data = Voyage::summary($args['from_date'], $args['to_date']);
    return ['summary' => $data->summary, 'records' => $data->rows];
});`,
    curl: `# return the client-side tool result to resume the run
curl -X POST \\
  https://api.maacc.dev/v1/agent-runs/run_8f3c/tool-results \\
  -H "Authorization: Bearer $MAACC_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{ "tool_call_id": "tc_01",
        "status": "completed",
        "result": { "summary": { "matching": 97 } } }'`,
  },
};

/* ---- tiny tokenizer ---- */
const KW = {
  ts:["import","from","const","let","var","await","async","function","return","new","export","if","else","typeof","true","false","null"],
  py:["from","import","def","return","if","not","in","as","with","class","None","True","False","and","or","lambda"],
  php:["use","function","new","return","echo","fn","if","else","true","false","null"],
  curl:["curl"],
};
function tokenizeLine(line, lang) {
  const commentRe = (lang === "py" || lang === "curl") ? "#[^\\n]*" : "\\/\\/[^\\n]*";
  const kws = (KW[lang] || []).join("|");
  const parts = [
    `(?<comment>${commentRe})`,
    `(?<string>"(?:\\\\.|[^"\\\\])*"|'(?:\\\\.|[^'\\\\])*')`,
    `(?<num>\\b\\d+(?:\\.\\d+)?\\b)`,
    `(?<var>\\$[A-Za-z_]\\w*)`,
    kws ? `(?<kw>\\b(?:${kws})\\b)` : `(?<kw>\\b\\0\\b)`,
    `(?<fn>[A-Za-z_]\\w*(?=\\s*\\())`,
    `(?<flag>(?<=\\s)-[A-Za-z]\\b)`,
  ];
  let re;
  try { re = new RegExp(parts.join("|"), "g"); }
  catch (e) { return [{ text: line, cls: "" }]; }
  const out = []; let last = 0, m;
  const clsFor = g => ({ comment:"tok-com", string:"tok-str", num:"tok-num", var:"tok-prop", kw:"tok-key", fn:"tok-fn", flag:"tok-key" }[g] || "");
  while ((m = re.exec(line)) !== null) {
    if (m.index > last) out.push({ text: line.slice(last, m.index), cls: "" });
    const g = m.groups ? Object.keys(m.groups).find(k => m.groups[k] != null) : null;
    out.push({ text: m[0], cls: clsFor(g) });
    last = m.index + m[0].length;
    if (m[0].length === 0) re.lastIndex++;
  }
  if (last < line.length) out.push({ text: line.slice(last), cls: "" });
  return out.length ? out : [{ text: line || " ", cls: "" }];
}

function Playground() {
  const [scen, setScen] = useState("run");
  const [lang, setLang] = useState("ts");
  const [copied, setCopied] = useState(false);
  const [reveal, setReveal] = useState(999);
  const revTimer = useRef(null);

  const code = SNIPPETS[scen][lang];
  const lines = code.split("\n");

  useEffect(() => {
    const reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduce) { setReveal(lines.length); return; }
    setReveal(0);
    let i = 0;
    clearInterval(revTimer.current);
    revTimer.current = setInterval(() => {
      i += 1; setReveal(i);
      if (i >= lines.length) clearInterval(revTimer.current);
    }, 42);
    return () => clearInterval(revTimer.current);
  }, [scen, lang]);

  const copy = () => { try { navigator.clipboard?.writeText(code); } catch(e){} setCopied(true); setTimeout(() => setCopied(false), 1400); };
  const curInstall = PG_LANGS.find(l => l.id === lang).install;

  return (
    <section id="docs" className="ls-section" style={{ position:"relative" }}>
      <div className="ls-wrap">
        <div style={{ display:"grid", gridTemplateColumns:"0.85fr 1.15fr", gap:44, alignItems:"start" }} className="pg-grid">
          {/* left rail */}
          <div>
            <SectionHead kicker="Developer experience"
              title="Three lines to your first agent"
              sub="A thin SDK in the languages your teams already use. Authenticate, register your tools, invoke an agent — MAACC does the orchestration." />
            <Reveal as="div" delay={2} style={{ marginTop:26, display:"grid", gap:14 }}>
              {[
                { n:"01", ic:"key", t:"Get project credentials", d:"Register a project in MAACC and copy its environment credentials." },
                { n:"02", ic:"sdk", t:"Install & register tools", d:"Add the SDK and implement the client-side tool contracts you defined." },
                { n:"03", ic:"play", t:"Invoke an agent", d:"Call runAgent — pause, tool execution and resume are automatic." },
              ].map(s => (
                <div key={s.n} style={{ display:"flex", gap:13 }}>
                  <span style={{ width:34, height:34, borderRadius:9, flexShrink:0, background:"var(--primary-soft)", color:"var(--primary)", display:"flex", alignItems:"center", justifyContent:"center" }}><Icon name={s.ic} size={17} /></span>
                  <div>
                    <div style={{ fontSize:14, fontWeight:700, display:"flex", alignItems:"center", gap:8 }}><span className="mono" style={{ color:"var(--text-3)", fontSize:12 }}>{s.n}</span>{s.t}</div>
                    <div style={{ fontSize:13, color:"var(--text-2)", marginTop:3, lineHeight:1.5 }}>{s.d}</div>
                  </div>
                </div>
              ))}
            </Reveal>
            <Reveal as="div" delay={3} style={{ marginTop:22 }}>
              <CopyChip text={curInstall} />
            </Reveal>
          </div>

          {/* code window */}
          <Reveal as="div" delay={1}>
            <div style={{ borderRadius:"var(--r-xl)", overflow:"hidden", border:"1px solid var(--code-border)", background:"var(--code-bg)", boxShadow:"var(--sh-lg)" }}>
              {/* scenario tabs */}
              <div style={{ display:"flex", gap:4, padding:"10px 12px 0", background:"var(--code-bg)", flexWrap:"wrap" }}>
                {PG_SCEN.map(s => (
                  <button key={s.id} onClick={() => setScen(s.id)} style={{
                    border:"none", cursor:"pointer", padding:"7px 12px", borderRadius:"8px 8px 0 0", fontSize:12.5, fontWeight:600,
                    fontFamily:"var(--font)", background: scen===s.id ? "rgba(255,255,255,.06)" : "transparent",
                    color: scen===s.id ? "#e7ecf5" : "#6b7a96", transition:"color .15s, background .15s" }}>{s.label}</button>
                ))}
              </div>
              {/* language tabs */}
              <div style={{ display:"flex", alignItems:"center", gap:2, padding:"8px 12px", borderBottom:"1px solid var(--code-border)", background:"rgba(255,255,255,.02)" }}>
                {PG_LANGS.map(l => (
                  <button key={l.id} onClick={() => setLang(l.id)} style={{
                    border:"none", cursor:"pointer", padding:"5px 11px", borderRadius:6, fontSize:12, fontWeight:600, fontFamily:"var(--mono)",
                    background: lang===l.id ? "var(--primary)" : "transparent", color: lang===l.id ? "#fff" : "#8fa0bd", transition:"all .15s" }}>{l.label}</button>
                ))}
                <div style={{ flex:1 }} />
                <button onClick={copy} style={{ display:"inline-flex", alignItems:"center", gap:6, border:"1px solid var(--code-border)", background:"transparent",
                  color: copied ? "var(--teal-400)" : "#8fa0bd", cursor:"pointer", padding:"5px 10px", borderRadius:6, fontSize:11.5, fontWeight:600, fontFamily:"var(--mono)" }}>
                  <Icon name={copied ? "check" : "copy"} size={13} />{copied ? "Copied" : "Copy"}
                </button>
              </div>
              {/* code */}
              <div style={{ padding:"16px 16px 18px", overflowX:"auto", minHeight:352 }}>
                <pre style={{ margin:0 }}><code className="ls-code" style={{ display:"block" }}>
                  {lines.map((ln, i) => {
                    const visible = i < reveal;
                    const isLast = i === reveal - 1;
                    return (
                      <div key={i} style={{ display:"flex", opacity: visible ? 1 : 0, transition:"opacity .18s", minHeight:"1.72em" }}>
                        <span style={{ width:26, flexShrink:0, textAlign:"right", marginRight:16, color:"#3f4d68", userSelect:"none" }}>{i+1}</span>
                        <span style={{ whiteSpace:"pre", minWidth:0 }}>
                          {visible && tokenizeLine(ln, lang).map((tk, j) => <span key={j} className={tk.cls}>{tk.text}</span>)}
                          {isLast && reveal < lines.length && <span className="caret" />}
                        </span>
                      </div>
                    );
                  })}
                </code></pre>
              </div>
            </div>
          </Reveal>
        </div>
      </div>
    </section>
  );
}

window.Playground = Playground;


/* ============================================================
   MAACC Landing — CENTERPIECE 4: tool-contract -> SDK stub gen
   Edit a contract; the JSON + handler stub regenerate live.
   ============================================================ */
const TYPE_OPTS = ["string", "string·date", "number", "boolean"];
const MODE_OPTS = [
  { id:"client", label:"Client-side" },
  { id:"hosted", label:"MAACC-hosted" },
  { id:"http", label:"Remote HTTP" },
  { id:"knowledge", label:"Knowledge" },
];
const SENS_OPTS = ["Internal", "Confidential", "Restricted"];
const STUB_LANGS = [{ id:"ts", label:"TypeScript" }, { id:"py", label:"Python" }, { id:"php", label:"PHP" }];

const camel = s => s.replace(/_([a-z0-9])/g, (_, c) => c.toUpperCase());
const baseType = t => t === "string·date" ? "string" : t;

function tokenizeJson(line) {
  const re = /("(?:\\.|[^"\\])*"(?=\s*:))|("(?:\\.|[^"\\])*")|(\b\d+(?:\.\d+)?\b)|(\btrue\b|\bfalse\b|\bnull\b)/g;
  const out = []; let last = 0, m;
  while ((m = re.exec(line)) !== null) {
    if (m.index > last) out.push({ text: line.slice(last, m.index), cls: "tok-punc" });
    const cls = m[1] ? "tok-prop" : m[2] ? "tok-str" : m[3] ? "tok-num" : "tok-key";
    out.push({ text: m[0], cls });
    last = m.index + m[0].length;
  }
  if (last < line.length) out.push({ text: line.slice(last), cls: "tok-punc" });
  return out.length ? out : [{ text: line || " ", cls: "" }];
}

function buildContract(s) {
  const props = s.params.map(p => {
    const b = baseType(p.type);
    return p.type === "string·date"
      ? `      "${p.name}": { "type": "string", "format": "date" }`
      : `      "${p.name}": { "type": "${b}" }`;
  }).join(",\n");
  const required = s.params.filter(p => p.required).map(p => `"${p.name}"`).join(", ");
  return `{
  "name": "${s.name}",
  "description": "Retrieves approved data from the owning app.",
  "execution_mode": "${s.mode === "client" ? "client_side" : s.mode}",
  "sensitivity": "${s.sensitivity}",
  "requires_approval": ${s.approval},
  "input_schema": {
    "type": "object",
    "properties": {
${props}
    },
    "required": [${required}]
  },
  "output_schema": {
    "type": "object",
    "properties": {
      "summary": { "type": "object" },
      "records": { "type": "array" }
    }
  }
}`;
}

function buildStub(s) {
  const argComment = s.params.map(p => `${p.name}${p.required ? "" : "?"}: ${baseType(p.type)}`).join("  ·  ");
  if (s.lang === "ts") {
    const call = s.params.map(p => `      ${camel(p.name)}: args.${p.name},`).join("\n");
    return `import { maacc } from "./maacc";

// contract "${s.name}" — implement it in your app
maacc.registerTool("${s.name}", async (args, ctx) => {
  // args:  ${argComment}
${s.approval ? `  // requires approval before production use\n` : ``}  if (!ctx.user.can("data:view")) {
    return { status: "rejected", reason: "not permitted" };
  }

  const result = await yourService.${camel(s.name)}({
${call}
  });

  return { summary: result.summary, records: result.records };
});`;
  }
  if (s.lang === "py") {
    const call = s.params.map(p => `        ${p.name}=args${p.required ? `["${p.name}"]` : `.get("${p.name}")`},`).join("\n");
    return `# contract "${s.name}" — implement it in your app
@maacc.tool("${s.name}")
def ${s.name.replace(/([A-Z])/g, "_$1").toLowerCase()}(args, ctx):
    # args:  ${argComment}
    if not ctx.user.can("data:view"):
        return {"status": "rejected", "reason": "not permitted"}

    result = your_service.${s.name.replace(/([A-Z])/g, "_$1").toLowerCase()}(
${call}
    )
    return {"summary": result.summary, "records": result.records}`;
  }
  // php
  const call = s.params.map(p => `        '${p.name}' => $args['${p.name}']${p.required ? "" : " ?? null"},`).join("\n");
  return `// contract "${s.name}" — implement it in your app
$maacc->registerTool('${s.name}', function ($args, $ctx) {
    // args:  ${argComment}
    if (! $ctx->user->can('data:view')) {
        return ['status' => 'rejected'];
    }

    $result = YourService::${s.name}([
${call}
    ]);
    return ['summary' => $result->summary, 'records' => $result->records];
});`;
}

/* small controls */
function MiniSeg({ options, value, onChange }) {
  return (
    <div style={{ display:"inline-flex", flexWrap:"wrap", gap:4, background:"var(--surface-3)", borderRadius:8, padding:3 }}>
      {options.map(o => {
        const id = o.id ?? o, label = o.label ?? o, on = id === value;
        return (
          <button key={id} onClick={() => onChange(id)} style={{
            border:"none", cursor:"pointer", padding:"6px 11px", borderRadius:6, fontSize:12, fontWeight:600, fontFamily:"var(--font)",
            background: on ? "var(--surface)" : "transparent", color: on ? "var(--text)" : "var(--text-3)",
            boxShadow: on ? "var(--sh-sm)" : "none", transition:"all .15s" }}>{label}</button>
        );
      })}
    </div>
  );
}

function StubGen() {
  const [name, setName] = useState("getOperationalRecords");
  const [mode, setMode] = useState("client");
  const [sensitivity, setSensitivity] = useState("Confidential");
  const [approval, setApproval] = useState(true);
  const [params, setParams] = useState([
    { name:"from_date", type:"string·date", required:true },
    { name:"to_date", type:"string·date", required:true },
    { name:"vessel_id", type:"string", required:false },
  ]);
  const [view, setView] = useState("contract");
  const [lang, setLang] = useState("ts");
  const [copied, setCopied] = useState(false);

  const state = { name, mode, sensitivity, approval, params, lang };
  const contract = buildContract(state);
  const stub = buildStub(state);
  const shown = view === "contract" ? contract : stub;
  const shownLines = shown.split("\n");

  const setParam = (i, patch) => setParams(ps => ps.map((p, j) => j === i ? { ...p, ...patch } : p));
  const addParam = () => setParams(ps => ps.length >= 6 ? ps : [...ps, { name:`param_${ps.length+1}`, type:"string", required:false }]);
  const rmParam = (i) => setParams(ps => ps.length <= 1 ? ps : ps.filter((_, j) => j !== i));
  const copy = () => { try { navigator.clipboard?.writeText(shown); } catch(e){} setCopied(true); setTimeout(() => setCopied(false), 1400); };

  const inp = { height:34, borderRadius:7, border:"1px solid var(--border-2)", background:"var(--surface)", color:"var(--text)",
    fontFamily:"var(--mono)", fontSize:12.5, padding:"0 10px", outline:"none" };

  return (
    <section id="generator" className="ls-section" style={{ position:"relative", background:"var(--surface-2)", borderTop:"1px solid var(--border)", borderBottom:"1px solid var(--border)" }}>
      <div className="ls-wrap">
        <SectionHead center kicker="Contract-first tooling"
          title="Design the contract. Generate the stub."
          sub="Define a tool once in MAACC as a typed contract — the SDK stub for your app is generated for you. Edit anything below and watch both update live." />

        <Reveal as="div" delay={1} style={{ marginTop:46 }}>
          <div style={{ display:"grid", gridTemplateColumns:"0.92fr 1.08fr", gap:22, alignItems:"stretch" }} className="gen-grid">
            {/* editor */}
            <div className="ls-card" style={{ padding:20 }}>
              <div style={{ display:"flex", alignItems:"center", gap:8, marginBottom:16 }}>
                <span style={{ width:30, height:30, borderRadius:8, background:"var(--surface-3)", color:"var(--orange-500)", display:"flex", alignItems:"center", justifyContent:"center" }}><Icon name="tools" size={16} /></span>
                <span style={{ fontSize:14, fontWeight:700 }}>Tool contract</span>
              </div>

              <label style={{ fontSize:11.5, fontWeight:600, color:"var(--text-3)", textTransform:"uppercase", letterSpacing:.4 }}>Tool name</label>
              <input value={name} onChange={e => setName(e.target.value.replace(/[^A-Za-z0-9_]/g, ""))} style={{ ...inp, width:"100%", marginTop:6, marginBottom:16 }} />

              <label style={{ fontSize:11.5, fontWeight:600, color:"var(--text-3)", textTransform:"uppercase", letterSpacing:.4, display:"block", marginBottom:7 }}>Execution mode</label>
              <MiniSeg options={MODE_OPTS} value={mode} onChange={setMode} />

              <div style={{ marginTop:18, display:"flex", alignItems:"center", justifyContent:"space-between", marginBottom:8 }}>
                <label style={{ fontSize:11.5, fontWeight:600, color:"var(--text-3)", textTransform:"uppercase", letterSpacing:.4 }}>Input parameters</label>
                <button onClick={addParam} disabled={params.length>=6} style={{ display:"inline-flex", alignItems:"center", gap:5, border:"1px solid var(--border-2)", background:"var(--surface)", color:"var(--primary)", borderRadius:7, padding:"4px 9px", fontSize:11.5, fontWeight:600, cursor: params.length>=6?"not-allowed":"pointer", opacity: params.length>=6?.5:1 }}><Icon name="plus" size={13} />Add</button>
              </div>
              <div style={{ display:"grid", gap:8 }}>
                {params.map((p, i) => (
                  <div key={i} style={{ display:"flex", gap:6, alignItems:"center" }}>
                    <input value={p.name} onChange={e => setParam(i, { name: e.target.value.replace(/[^A-Za-z0-9_]/g, "") })} style={{ ...inp, flex:1, minWidth:0 }} />
                    <select value={p.type} onChange={e => setParam(i, { type: e.target.value })} style={{ ...inp, cursor:"pointer", flexShrink:0 }}>
                      {TYPE_OPTS.map(t => <option key={t} value={t}>{t}</option>)}
                    </select>
                    <button onClick={() => setParam(i, { required: !p.required })} title="required" style={{
                      flexShrink:0, height:34, padding:"0 10px", borderRadius:7, fontSize:11, fontWeight:700, cursor:"pointer", fontFamily:"var(--mono)",
                      border:`1px solid ${p.required ? "var(--primary)" : "var(--border-2)"}`, background: p.required ? "var(--primary-soft)" : "var(--surface)", color: p.required ? "var(--primary)" : "var(--text-3)" }}>req</button>
                    <button onClick={() => rmParam(i)} disabled={params.length<=1} style={{ flexShrink:0, width:34, height:34, borderRadius:7, border:"1px solid var(--border-2)", background:"var(--surface)", color:"var(--text-3)", cursor: params.length<=1?"not-allowed":"pointer", display:"flex", alignItems:"center", justifyContent:"center", opacity: params.length<=1?.4:1 }}><Icon name="x" size={14} /></button>
                  </div>
                ))}
              </div>

              <div style={{ display:"grid", gridTemplateColumns:"1fr", gap:16, marginTop:18 }}>
                <div>
                  <label style={{ fontSize:11.5, fontWeight:600, color:"var(--text-3)", textTransform:"uppercase", letterSpacing:.4, display:"block", marginBottom:7 }}>Data sensitivity</label>
                  <MiniSeg options={SENS_OPTS} value={sensitivity} onChange={setSensitivity} />
                </div>
                <label style={{ display:"flex", alignItems:"center", gap:10, cursor:"pointer" }}>
                  <button onClick={() => setApproval(a => !a)} style={{ width:40, height:23, borderRadius:999, border:"none", cursor:"pointer", padding:0, background: approval ? "var(--primary)" : "var(--border-2)", position:"relative", transition:"background .15s", flexShrink:0 }}>
                    <span style={{ position:"absolute", top:2, left: approval ? 20 : 2, width:19, height:19, borderRadius:999, background:"#fff", transition:"left .16s", boxShadow:"0 1px 3px rgba(0,0,0,.3)" }} />
                  </button>
                  <span style={{ fontSize:13, fontWeight:600 }}>Requires approval before production</span>
                </label>
              </div>
            </div>

            {/* output */}
            <div style={{ borderRadius:"var(--r-xl)", overflow:"hidden", border:"1px solid var(--code-border)", background:"var(--code-bg)", display:"flex", flexDirection:"column", boxShadow:"var(--sh-md)" }}>
              <div style={{ display:"flex", alignItems:"center", gap:4, padding:"10px 12px", borderBottom:"1px solid var(--code-border)" }}>
                <button onClick={() => setView("contract")} style={{ border:"none", cursor:"pointer", padding:"6px 12px", borderRadius:7, fontSize:12.5, fontWeight:600, background: view==="contract"?"rgba(255,255,255,.06)":"transparent", color: view==="contract"?"#e7ecf5":"#6b7a96", display:"inline-flex", alignItems:"center", gap:6 }}><Icon name="doc" size={14} />Contract · JSON</button>
                <button onClick={() => setView("stub")} style={{ border:"none", cursor:"pointer", padding:"6px 12px", borderRadius:7, fontSize:12.5, fontWeight:600, background: view==="stub"?"rgba(255,255,255,.06)":"transparent", color: view==="stub"?"#e7ecf5":"#6b7a96", display:"inline-flex", alignItems:"center", gap:6 }}><Icon name="code" size={14} />Handler stub</button>
                <div style={{ flex:1 }} />
                <button onClick={copy} style={{ display:"inline-flex", alignItems:"center", gap:6, border:"1px solid var(--code-border)", background:"transparent", color: copied?"var(--teal-400)":"#8fa0bd", cursor:"pointer", padding:"5px 10px", borderRadius:6, fontSize:11.5, fontWeight:600, fontFamily:"var(--mono)" }}><Icon name={copied?"check":"copy"} size={13} />{copied?"Copied":"Copy"}</button>
              </div>
              {view === "stub" && (
                <div style={{ display:"flex", gap:2, padding:"8px 12px", borderBottom:"1px solid var(--code-border)", background:"rgba(255,255,255,.02)" }}>
                  {STUB_LANGS.map(l => (
                    <button key={l.id} onClick={() => setLang(l.id)} style={{ border:"none", cursor:"pointer", padding:"4px 10px", borderRadius:6, fontSize:11.5, fontWeight:600, fontFamily:"var(--mono)", background: lang===l.id?"var(--primary)":"transparent", color: lang===l.id?"#fff":"#8fa0bd" }}>{l.label}</button>
                  ))}
                </div>
              )}
              <div style={{ padding:"14px 16px", overflow:"auto", flex:1, maxHeight:520 }}>
                <pre style={{ margin:0 }}><code className="ls-code" style={{ display:"block" }}>
                  {shownLines.map((ln, i) => {
                    const toks = view === "contract" ? tokenizeJson(ln) : tokenizeLine(ln, lang);
                    return (
                      <div key={i} style={{ display:"flex", minHeight:"1.72em" }}>
                        <span style={{ width:22, flexShrink:0, textAlign:"right", marginRight:14, color:"#3f4d68", userSelect:"none" }}>{i+1}</span>
                        <span style={{ whiteSpace:"pre" }}>{toks.map((tk, j) => <span key={j} className={tk.cls}>{tk.text}</span>)}</span>
                      </div>
                    );
                  })}
                </code></pre>
              </div>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}

window.StubGen = StubGen;


/* ============================================================
   MAACC Landing — features grid, trust band, stats, quotes
   ============================================================ */
function FeatureGrid() {
  return (
    <section className="ls-section" style={{ position:"relative" }}>
      <div className="ls-glow" style={{ width:420, height:420, top:120, left:"-8%", background:"var(--glow-a)", opacity:.4 }} />
      <div className="ls-wrap" style={{ position:"relative" }}>
        <SectionHead center kicker="One platform, end to end"
          title="Everything teams need to ship agents safely"
          sub="From the model catalog to the audit trail — MAACC covers the whole lifecycle so application teams stop rebuilding AI plumbing." />
        <div style={{ display:"grid", gridTemplateColumns:"repeat(auto-fit,minmax(280px,1fr))", gap:18, marginTop:52 }}>
          {MAACC.features.map((f, i) => (
            <Reveal key={f.title} as="div" delay={(i % 3) + 1}>
              <div className="ls-card ls-card-glow" style={{ padding:24, height:"100%" }}>
                <div style={{ width:46, height:46, borderRadius:12, display:"flex", alignItems:"center", justifyContent:"center",
                  background:`color-mix(in srgb, ${f.tone} 14%, transparent)`, color:f.tone, marginBottom:16 }}>
                  <Icon name={f.icon} size={23} />
                </div>
                <h3 style={{ margin:0, fontSize:18, fontWeight:700, letterSpacing:-.3 }}>{f.title}</h3>
                <p style={{ margin:"9px 0 0", fontSize:14, color:"var(--text-2)", lineHeight:1.6 }}>{f.desc}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}

function TrustBand() {
  const items = MAACC.logos;
  const row = [...items, ...items];
  return (
    <div style={{ padding:"38px 0", borderTop:"1px solid var(--border)", borderBottom:"1px solid var(--border)", background:"var(--surface-2)", overflow:"hidden" }}>
      <div className="ls-wrap">
        <div className="ls-kicker" style={{ fontSize:11, textAlign:"center", marginBottom:22 }}>Powering AI across internal platforms</div>
      </div>
      <div style={{ position:"relative", maskImage:"linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent)", WebkitMaskImage:"linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent)" }}>
        <div className="ls-marquee" style={{ gap:0 }}>
          {row.map((name, i) => (
            <div key={i} style={{ display:"flex", alignItems:"center", gap:10, padding:"0 34px", flexShrink:0, color:"var(--text-2)" }}>
              <span style={{ width:30, height:30, borderRadius:8, background:"var(--surface-3)", display:"flex", alignItems:"center", justifyContent:"center", color:"var(--text-3)" }}><Icon name="building" size={16} /></span>
              <span style={{ fontSize:16, fontWeight:700, letterSpacing:-.2, whiteSpace:"nowrap" }}>{name}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function StatBand() {
  return (
    <section className="ls-section" style={{ paddingTop:96, paddingBottom:96, position:"relative", overflow:"hidden" }}>
      <div style={{ position:"absolute", inset:0, background:"linear-gradient(180deg, var(--bg), var(--hero-3))" }} />
      <div className="ls-gridbg" style={{ opacity:.6 }} />
      <div className="ls-wrap" style={{ position:"relative" }}>
        <div style={{ display:"grid", gridTemplateColumns:"repeat(auto-fit,minmax(200px,1fr))", gap:20 }}>
          {MAACC.stats.map((s, i) => (
            <Reveal key={i} as="div" delay={(i % 4) + 1} style={{ textAlign:"center" }}>
              <div className="ls-grad" style={{ fontSize:"clamp(38px,5vw,58px)", fontWeight:700, letterSpacing:-2, lineHeight:1 }}>
                <CountUp end={s.end} decimals={s.decimals || 0} suffix={s.suffix || ""} />
              </div>
              <div style={{ fontSize:14.5, fontWeight:600, color:"var(--text)", marginTop:12 }}>{s.label}</div>
              <div style={{ fontSize:12.5, color:"var(--text-3)", marginTop:3 }}>{s.sub}</div>
            </Reveal>
          ))}
        </div>
        <Reveal as="div" delay={2} style={{ textAlign:"center", marginTop:34 }}>
          <span className="ls-chip" style={{ color:"var(--text-3)" }}><Icon name="info" size={13} /> Illustrative figures for demonstration</span>
        </Reveal>
      </div>
    </section>
  );
}

function Quotes() {
  const quotes = [
    { q:"MAACC took our agent integration from a six-week project to an afternoon. The data never leaves our service — that's what got it through security review.",
      name:"Reema Saleh", role:"Staff Engineer · Marine Operations", tone:"var(--teal-500)" },
    { q:"One catalog, one set of policies, every model. We finally have AI capabilities our auditors are genuinely comfortable signing off on.",
      name:"Aisha Rahman", role:"Head of Finance Systems", tone:"var(--primary)" },
  ];
  return (
    <section className="ls-section" style={{ paddingTop:40 }}>
      <div className="ls-wrap">
        <div style={{ display:"grid", gridTemplateColumns:"repeat(auto-fit,minmax(320px,1fr))", gap:20 }}>
          {quotes.map((c, i) => (
            <Reveal key={i} as="div" delay={(i % 2) + 1}>
              <div className="ls-card" style={{ padding:28, height:"100%", display:"flex", flexDirection:"column" }}>
                <Icon name="sparkles" size={22} style={{ color:c.tone }} />
                <p style={{ fontSize:"clamp(16px,1.7vw,19px)", lineHeight:1.55, color:"var(--text)", margin:"16px 0 0", fontWeight:500, letterSpacing:-.2 }}>“{c.q}”</p>
                <div style={{ flex:1 }} />
                <div style={{ display:"flex", alignItems:"center", gap:12, marginTop:22 }}>
                  <div style={{ width:42, height:42, borderRadius:12, background:c.tone, color:"#fff", display:"flex", alignItems:"center", justifyContent:"center", fontWeight:700, fontSize:16, flexShrink:0 }}>
                    {c.name.split(" ").map(w => w[0]).slice(0,2).join("")}
                  </div>
                  <div>
                    <div style={{ fontSize:14, fontWeight:700 }}>{c.name}</div>
                    <div style={{ fontSize:12.5, color:"var(--text-3)" }}>{c.role}</div>
                  </div>
                </div>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}

Object.assign(window, { FeatureGrid, TrustBand, StatBand, Quotes });


/* ============================================================
   MAACC Landing — pricing, estimator, onboarding, CTA, footer
   ============================================================ */
const TIERS = [
  { name:"Developer", price:"$0", unit:"free forever", blurb:"For prototyping and evaluation.",
    cta:"Start free", variant:"ghost", href:"/register", tone:"var(--teal-500)",
    feats:["Up to 1,000 agent runs / month","Sandbox environment","Full approved-model catalog","Agent playground","7-day run history","Community support"] },
  { name:"Team", price:"$0.02", unit:"/ agent run", note:"+ model tokens billed at provider cost", blurb:"For teams shipping to production.",
    cta:"Start building", variant:"primary", href:"/register", tone:"var(--primary)", featured:true,
    feats:["Everything in Developer","Unlimited agents & projects","Client-side tools & SDK","RBAC, approvals & policies","90-day audit logs & exports","Email + Slack support"] },
  { name:"Enterprise", price:"Let's talk", unit:"custom", blurb:"For org-wide governance and scale.",
    cta:"Contact sales", variant:"ghost", href:"#onboarding", tone:"var(--orange-500)",
    feats:["Everything in Team","On-prem & private models","SSO / SCIM provisioning","Data residency & retention controls","Dedicated environments & SLA","Solutions engineering"] },
];

function Estimator() {
  const [runs, setRuns] = useState(120000);
  const cost = runs * 0.02;
  const fmt = n => n >= 1000 ? (n/1000).toFixed(n % 1000 === 0 ? 0 : 1) + "K" : String(n);
  return (
    <Reveal as="div" delay={2} style={{ marginTop:26 }}>
      <div className="ls-card est-grid" style={{ padding:"22px 24px", display:"grid", gridTemplateColumns:"1.4fr 1fr", gap:26, alignItems:"center" }}>
        <div>
          <div style={{ display:"flex", alignItems:"center", justifyContent:"space-between", marginBottom:6 }}>
            <span style={{ fontSize:13.5, fontWeight:700, display:"flex", alignItems:"center", gap:8 }}><Icon name="gauge" size={16} style={{ color:"var(--primary)" }} /> Estimate your monthly bill</span>
            <span className="mono tnum" style={{ fontSize:13, color:"var(--text-2)" }}>{fmt(runs)} runs/mo</span>
          </div>
          <input type="range" min={1000} max={2000000} step={1000} value={runs} onChange={e => setRuns(+e.target.value)}
            style={{ width:"100%", accentColor:"var(--primary)", cursor:"pointer" }} />
          <div style={{ display:"flex", justifyContent:"space-between", fontSize:11, color:"var(--text-3)", marginTop:4 }}><span>1K</span><span>2M</span></div>
        </div>
        <div className="est-right" style={{ textAlign:"right", borderLeft:"1px solid var(--border)", paddingLeft:24 }}>
          <div style={{ fontSize:11.5, color:"var(--text-3)", fontWeight:600, textTransform:"uppercase", letterSpacing:.4 }}>Est. platform cost</div>
          <div className="ls-grad tnum" style={{ fontSize:"clamp(30px,4vw,42px)", fontWeight:700, letterSpacing:-1.5, lineHeight:1.1 }}>${cost.toLocaleString(undefined,{maximumFractionDigits:0})}</div>
          <div style={{ fontSize:11.5, color:"var(--text-3)" }}>+ model tokens at cost</div>
        </div>
      </div>
    </Reveal>
  );
}

function Pricing() {
  return (
    <section id="pricing" className="ls-section" style={{ position:"relative" }}>
      <div className="ls-glow" style={{ width:440, height:440, top:60, right:"-8%", background:"var(--glow-a)", opacity:.35 }} />
      <div className="ls-wrap" style={{ position:"relative" }}>
        <SectionHead center kicker="Pricing"
          title="Usage-based. Pay for runs, not seats."
          sub="Start free, then pay per agent run as you scale. Model tokens pass through at provider cost — no markup, no surprises." />

        <div style={{ display:"grid", gridTemplateColumns:"repeat(auto-fit,minmax(280px,1fr))", gap:20, marginTop:52, alignItems:"stretch" }}>
          {TIERS.map((t, i) => (
            <Reveal key={t.name} as="div" delay={(i % 3) + 1} style={{ height:"100%" }}>
              <div className="ls-card" style={{ padding:26, height:"100%", display:"flex", flexDirection:"column", position:"relative",
                borderColor: t.featured ? "var(--primary)" : "var(--border)",
                boxShadow: t.featured ? "var(--sh-glow)" : "var(--sh-sm)",
                background: t.featured ? "var(--surface)" : "var(--surface)" }}>
                {t.featured && <span style={{ position:"absolute", top:-11, left:26, background:"var(--primary)", color:"var(--primary-contrast)", fontSize:11, fontWeight:700, padding:"3px 11px", borderRadius:999, letterSpacing:.3 }}>MOST POPULAR</span>}
                <div style={{ display:"flex", alignItems:"center", gap:8 }}>
                  <span style={{ width:9, height:9, borderRadius:9, background:t.tone }} />
                  <span style={{ fontSize:16, fontWeight:700 }}>{t.name}</span>
                </div>
                <div style={{ marginTop:16, display:"flex", alignItems:"baseline", gap:8 }}>
                  <span style={{ fontSize:"clamp(30px,4vw,40px)", fontWeight:700, letterSpacing:-1.5, lineHeight:1 }}>{t.price}</span>
                  <span style={{ fontSize:13.5, color:"var(--text-3)", fontWeight:500 }}>{t.unit}</span>
                </div>
                <div style={{ fontSize:12, color:"var(--text-3)", marginTop:6, minHeight:17 }}>{t.note || ""}</div>
                <p style={{ fontSize:13.5, color:"var(--text-2)", margin:"10px 0 0" }}>{t.blurb}</p>
                <div style={{ height:1, background:"var(--border)", margin:"18px 0" }} />
                <div style={{ display:"grid", gap:11, flex:1 }}>
                  {t.feats.map(f => (
                    <div key={f} style={{ display:"flex", gap:9, fontSize:13.5, color:"var(--text-2)" }}>
                      <Icon name="check2" size={16} strokeWidth={2.2} style={{ color:t.tone, flexShrink:0, marginTop:2 }} />{f}
                    </div>
                  ))}
                </div>
                <Btn href={t.href} variant={t.variant} icon={t.variant==="primary"?"arrowRight":undefined} style={{ marginTop:22, width:"100%", flexDirection: t.variant==="primary"?"row-reverse":"row" }}>{t.cta}</Btn>
              </div>
            </Reveal>
          ))}
        </div>

        <Estimator />
      </div>
    </section>
  );
}

function Onboarding() {
  const steps = [
    { n:"01", ic:"building", t:"Register your application", d:"Create a project in MAACC and get environment credentials for dev, staging and production." },
    { n:"02", ic:"sdk", t:"Install the SDK & implement tools", d:"Add the SDK, then implement the client-side tool contracts inside your own codebase." },
    { n:"03", ic:"send", t:"Publish & invoke", d:"Test in the playground, publish your agent, and call it from your app. That's it." },
  ];
  return (
    <section id="onboarding" className="ls-section" style={{ position:"relative", background:"var(--surface-2)", borderTop:"1px solid var(--border)" }}>
      <div className="ls-wrap">
        <SectionHead center kicker="Onboarding"
          title="Live in three steps"
          sub="No infrastructure to stand up. Register, integrate, ship — most teams run their first governed agent the same day." />
        <div style={{ display:"grid", gridTemplateColumns:"repeat(auto-fit,minmax(260px,1fr))", gap:18, marginTop:50 }}>
          {steps.map((s, i) => (
            <Reveal key={s.n} as="div" delay={(i % 3) + 1}>
              <div className="ls-card" style={{ padding:24, height:"100%" }}>
                <div style={{ display:"flex", alignItems:"center", justifyContent:"space-between" }}>
                  <span style={{ width:44, height:44, borderRadius:12, background:"var(--primary-soft)", color:"var(--primary)", display:"flex", alignItems:"center", justifyContent:"center" }}><Icon name={s.ic} size={22} /></span>
                  <span className="mono" style={{ fontSize:28, fontWeight:700, color:"var(--text-3)", opacity:.9 }}>{s.n}</span>
                </div>
                <h3 style={{ margin:"16px 0 0", fontSize:17, fontWeight:700 }}>{s.t}</h3>
                <p style={{ margin:"8px 0 0", fontSize:13.5, color:"var(--text-2)", lineHeight:1.6 }}>{s.d}</p>
              </div>
            </Reveal>
          ))}
        </div>
        <Reveal as="div" delay={2} style={{ display:"flex", gap:12, justifyContent:"center", marginTop:36, flexWrap:"wrap" }}>
          <Btn href="/register" icon="arrowRight" style={{ flexDirection:"row-reverse" }}>Create your project</Btn>
          <Btn href="#docs" variant="ghost" icon="book">Browse the docs</Btn>
        </Reveal>
      </div>
    </section>
  );
}

function FinalCTA() {
  return (
    <section className="ls-section" style={{ position:"relative", overflow:"hidden", paddingTop:120, paddingBottom:120 }}>
      <div style={{ position:"absolute", inset:0, background:"linear-gradient(160deg, var(--hero-1), var(--hero-2))" }} />
      <div className="ls-gridbg" />
      {window.NodeField && <NodeField density={0.8} speed={0.9} />}
      <div className="ls-glow" style={{ width:520, height:520, top:-120, left:"50%", transform:"translateX(-50%)", background:"var(--glow-a)", animation:"glowPulse 10s ease-in-out infinite" }} />
      <div className="ls-wrap" style={{ position:"relative", textAlign:"center", zIndex:2 }}>
        <Reveal as="div"><LogoMark size={54} /></Reveal>
        <Reveal as="h2" delay={1} style={{ fontSize:"clamp(32px,5vw,58px)", fontWeight:700, letterSpacing:-1.8, lineHeight:1.05, margin:"22px auto 0", maxWidth:760, textWrap:"balance" }}>
          Bring every agent under <span className="ls-grad">one control centre</span>
        </Reveal>
        <Reveal as="p" delay={2} className="ls-sub" style={{ margin:"18px auto 0", textAlign:"center" }}>
          Orchestrate models, tools and runs from a single platform — with your data exactly where it belongs.
        </Reveal>
        <Reveal as="div" delay={3} style={{ display:"flex", gap:13, justifyContent:"center", flexWrap:"wrap", marginTop:32 }}>
          <Btn href="/register" icon="arrowRight" style={{ flexDirection:"row-reverse" }}>Start building free</Btn>
          <Btn href="#pricing" variant="glass">See pricing</Btn>
        </Reveal>
        <Reveal as="div" delay={4} style={{ marginTop:22, display:"flex", justifyContent:"center" }}>
          <CopyChip text="npm i @maacc/sdk" />
        </Reveal>
      </div>
    </section>
  );
}

function Footer() {
  const cols = [
    { title:"Platform", links:[["Architecture","#platform"],["How it works","#how"],["Pricing","#pricing"],["Onboarding","#onboarding"]] },
    { title:"Developers", links:[["Docs","#docs"],["SDK reference","#docs"],["Tool contracts","#generator"],["Playground","/register"]] },
    { title:"Company", links:[["About","#top"],["Security","#platform"],["Governance","#platform"],["Contact","#onboarding"]] },
    { title:"Account", links:[["Log in","/register"],["Start free","/register"],["Status","#top"]] },
  ];
  return (
    <footer style={{ borderTop:"1px solid var(--border)", background:"var(--surface-2)", padding:"56px 0 34px" }}>
      <div className="ls-wrap">
        <div style={{ display:"grid", gridTemplateColumns:"1.6fr repeat(4, 1fr)", gap:32 }} className="foot-grid">
          <div>
            <Wordmark onNav={() => {}} />
            <p style={{ fontSize:13.5, color:"var(--text-2)", lineHeight:1.6, margin:"16px 0 0", maxWidth:280 }}>
              The Multi AI Agent Control Centre — orchestrate agents, models and tools from one governed platform.
            </p>
            <div style={{ display:"flex", alignItems:"center", gap:9, marginTop:18 }}>
              <span style={{ width:8, height:8, borderRadius:8, background:"var(--teal-500)", boxShadow:"0 0 0 3px color-mix(in srgb, var(--teal-500) 22%, transparent)" }} />
              <span style={{ fontSize:12.5, color:"var(--text-2)" }}>All systems operational</span>
            </div>
          </div>
          {cols.map(c => (
            <div key={c.title}>
              <div style={{ fontSize:12, fontWeight:700, color:"var(--text-3)", textTransform:"uppercase", letterSpacing:.6, marginBottom:14 }}>{c.title}</div>
              <div style={{ display:"grid", gap:10 }}>
                {c.links.map(([label, href]) => (
                  <a key={label} href={href} className="foot-link" style={{ fontSize:13.5, color:"var(--text-2)", transition:"color .15s", width:"fit-content" }}>{label}</a>
                ))}
              </div>
            </div>
          ))}
        </div>
        <div style={{ display:"flex", alignItems:"center", justifyContent:"space-between", gap:16, flexWrap:"wrap", marginTop:44, paddingTop:22, borderTop:"1px solid var(--border)" }}>
          <span style={{ fontSize:12.5, color:"var(--text-3)" }}>© 2026 MAACC · Multi AI Agent Control Centre. Illustrative product concept.</span>
          <div style={{ display:"flex", gap:20 }}>
            <a href="#top" className="foot-link" style={{ fontSize:12.5, color:"var(--text-3)" }}>Privacy</a>
            <a href="#top" className="foot-link" style={{ fontSize:12.5, color:"var(--text-3)" }}>Terms</a>
            <a href="#top" className="foot-link" style={{ fontSize:12.5, color:"var(--text-3)" }}>Security</a>
          </div>
        </div>
      </div>
    </footer>
  );
}

Object.assign(window, { Pricing, Onboarding, FinalCTA, Footer });


/* ============================================================
   MAACC Landing — app root
   ============================================================ */
function LandingApp() {
  const themeState = useThemeState();
  return (
    <ThemeCtx.Provider value={themeState}>
      <TopNav />
      <main>
        <Hero />
        <TrustBand />
        <Architecture />
        <FeatureGrid />
        <Simulator />
        <Playground />
        <StubGen />
        <StatBand />
        <Quotes />
        <Pricing />
        <Onboarding />
        <FinalCTA />
      </main>
      <Footer />
    </ThemeCtx.Provider>
  );
}



export default function Welcome() {
  return (
    <>
      <Head title="MAACC — Multi Agent AI Control Centre">
        <meta name="description" content="MAACC is the control centre for enterprise AI agents — orchestrate models, tools and runs from one governed platform while your data stays in your app." />
      </Head>
      <LandingApp />
    </>
  );
}

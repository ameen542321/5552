import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

const chromeBin = process.env.CHROME_BIN?.trim().replace(/^['"]|['"]$/g, '');
const windowsBrowsers = process.platform === 'win32' ? [
    process.env.PROGRAMFILES && path.join(process.env.PROGRAMFILES, 'Google/Chrome/Application/chrome.exe'),
    process.env['PROGRAMFILES(X86)'] && path.join(process.env['PROGRAMFILES(X86)'], 'Google/Chrome/Application/chrome.exe'),
    process.env.LOCALAPPDATA && path.join(process.env.LOCALAPPDATA, 'Google/Chrome/Application/chrome.exe'),
    process.env.PROGRAMFILES && path.join(process.env.PROGRAMFILES, 'Microsoft/Edge/Application/msedge.exe'),
] : [];
const browsers = [chromeBin, ...windowsBrowsers, 'chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable'].filter(Boolean);
const browser = browsers.find((candidate) => spawnSync(candidate, ['--version'], { encoding: 'utf8', windowsHide: true }).status === 0);
if (!browser) {
    console.error(`Browser contrast check requires Chromium/Chrome. Checked:\n- ${browsers.join('\n- ')}\nSet CHROME_BIN to the complete browser executable path.`);
    process.exit(2);
}

const manifest = JSON.parse(fs.readFileSync('public/build/manifest.json', 'utf8'));
const cssPath = path.join('public/build', manifest['resources/css/app.css'].file);
const css = fs.readFileSync(cssPath, 'utf8');
const stateCss = css.replaceAll(':hover', '.contrast-hover').replaceAll(':focus-visible', '.contrast-focus');
const samples = [
    ['text-primary', 'ui-card', 'نص أساسي', 'base', 4.5],
    ['text-secondary', 'ui-card ui-text-soft', 'نص ثانوي', 'base', 4.5],
    ['text-muted', 'ui-card ui-text-muted', 'نص هادئ', 'base', 4.5],
    ['badge-danger', 'ui-badge ui-badge-danger', 'خطر', 'base', 4.5],
    ['badge-warning', 'ui-badge ui-badge-warning', 'تحذير', 'base', 4.5],
    ['badge-info', 'ui-badge ui-badge-info', 'معلومة', 'base', 4.5],
    ['primary-hover', 'ui-btn ui-btn-primary contrast-hover', 'إجراء', 'hover', 3],
    ['secondary-focus', 'ui-btn ui-btn-secondary contrast-focus', 'إجراء', 'focus', 3],
    ['disabled', 'ui-btn ui-btn-secondary disabled:opacity-50', 'معطل', 'disabled', 3],
];
const escapedCss = `${css}\n${stateCss}`.replaceAll('</style', '<\\/style');
const renderFixture = (theme) => `<!doctype html><html lang="ar" dir="rtl" class="${theme}"><head><meta charset="utf-8"><style>${escapedCss}</style></head><body><section data-theme="${theme}"><div class="ui-card">${samples.map(([id, classes, text, state]) => `<button data-id="${id}" data-state="${state}" class="${classes}"${state === 'disabled' ? ' disabled' : ''}>${text}</button>`).join('')}</div></section><pre id="contrast-result"></pre><script>
const parse = value => (value.match(/[\\d.]+/g) || []).map(Number);
const blend = (front, back) => { const a=(front[3] ?? 1); return front.slice(0,3).map((v,i)=>v*a+back[i]*(1-a)); };
const luminance = rgb => { const c=rgb.map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4); return .2126*c[0]+.7152*c[1]+.0722*c[2]; };
const ratio = (a,b) => { const x=luminance(a), y=luminance(b); return (Math.max(x,y)+.05)/(Math.min(x,y)+.05); };
const background = el => { let color=[255,255,255,1]; const chain=[]; for(let n=el;n;n=n.parentElement) chain.unshift(n); for(const n of chain){ const parsed=parse(getComputedStyle(n).backgroundColor); if(parsed.length) color=[...blend(parsed,color),1]; } return color; };
const thresholds=${JSON.stringify(Object.fromEntries(samples.map(([id,,, ,minimum]) => [id, minimum])))};
const results=[]; document.querySelectorAll('[data-id]').forEach(el=>{ const style=getComputedStyle(el); const backdrop=background(el.parentElement); const paintedBg=blend(parse(style.backgroundColor),backdrop); const paintedFg=blend(parse(style.color),paintedBg); const opacity=Number(style.opacity); const bg=blend([...paintedBg,opacity],backdrop); const fg=blend([...paintedFg,opacity],backdrop); const value=ratio(fg,bg); results.push({theme:el.closest('[data-theme]').dataset.theme,id:el.dataset.id,state:el.dataset.state,ratio:Number(value.toFixed(2)),minimum:thresholds[el.dataset.id],pass:value>=thresholds[el.dataset.id]}); });
document.getElementById('contrast-result').textContent=JSON.stringify(results);
</script></body></html>`;
const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ui-contrast-'));
const results = [];
for (const theme of ['dark', 'light']) {
    const fixture = path.join(tempDir, `${theme}.html`);
    fs.writeFileSync(fixture, renderFixture(theme));
    const run = spawnSync(browser, ['--headless=new', '--no-sandbox', '--disable-gpu', '--dump-dom', pathToFileURL(fixture).href], { encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 });
    if (run.status !== 0) { console.error(run.stderr || run.stdout); process.exit(1); }
    const match = run.stdout.match(/<pre id="contrast-result">([^<]+)<\/pre>/);
    if (!match) { console.error(`Browser did not return ${theme} contrast results.`); process.exit(1); }
    results.push(...JSON.parse(match[1].replaceAll('&quot;', '"').replaceAll('&amp;', '&')));
}
fs.rmSync(tempDir, { recursive: true, force: true });
for (const item of results) console.log(`${item.pass ? 'PASS' : 'FAIL'} ${item.theme}/${item.id}/${item.state}: ${item.ratio}:1 (min ${item.minimum}:1)`);
if (results.some(item => !item.pass)) process.exit(1);

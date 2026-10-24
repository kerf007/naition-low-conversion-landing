'use strict';
let data;
let selected;
let control;
let diagnosticExports = [];
const $ = (id) => document.getElementById(id);
const number = (n) => new Intl.NumberFormat('ru-RU', {maximumFractionDigits: 1}).format(n);
const pct = (n) => n == null ? '—' : `${number(n * 100)}%`;
const signed = (n) => `${n > 0 ? '+' : n < 0 ? '−' : ''}${number(Math.abs(n) * 100)}`;
const esc = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const rate = (a, b) => b > 0 ? a / b : null;

function metric(label, value, sub, primary = false) {
  return `<article class="metric${primary ? ' primary' : ''}"><div class="label">${esc(label)}</div><div class="value">${esc(value)}</div><div class="sub">${esc(sub)}</div></article>`;
}

function render() {
  const run = data.runs.find(r => r.id === selected);
  const base = data.runs.find(r => r.id === control);
  const comparison = data.comparisons[`${selected}:${control}`];
  $('run-select').value = selected;
  $('control-select').value = control;
  const c = run.client;
  $('metrics').innerHTML = metric('Конверсия по серверу', pct(run.rate), `Заявки: ${number(run.orders)} · Визиты: ${number(run.visits)}`, true)
    + metric('Разница с контролем', comparison ? `${signed(comparison.delta)} п.п.` : '—', comparison ? `Контроль: ${pct(base.rate)}` : 'Выбран тот же прогон')
    + metric('CTA + начало ввода', c ? pct(rate(c.starts,c.cta)) : '—', c ? `${number(c.starts)} из ${number(c.cta)} нажавших` : 'Телеметрии нет')
    + metric('CTA без начала ввода', c ? number(c.cta-c.starts) : '—', c ? 'Наблюдаемые клиентские визиты' : 'Телеметрии нет');
  renderChart();
  renderFunnel(run, base);
  $('change').textContent = run.change;
  $('run-meta').innerHTML = [run.commit, `${number(run.price)} ₽`, run.fields].map(t=>`<span>${esc(t)}</span>`).join('');
  if (!comparison) {
    $('comparison').innerHTML = '<div class="verdict"><strong>Выбран один и тот же прогон</strong><p>Выберите другой контроль, чтобы сравнить результаты.</p></div>';
  } else {
    const convincing = comparison.p < .05;
    const title = convincing ? (comparison.delta > 0 ? 'Есть сигнал роста' : 'Есть сигнал снижения') : 'Убедительного различия пока нет';
    const explanation = convincing ? 'Различие заметно в этих выборках. Последовательные прогоны не устанавливают причину изменения.' : 'Наблюдаемая разница может быть разбросом. Это не доказывает равенство вариантов.';
    $('comparison').innerHTML = `<div class="verdict"><strong>${title}</strong><p>${explanation}</p></div><p class="compare-detail">95% интервал разницы: <strong>${signed(comparison.interval[0])}…${signed(comparison.interval[1])} п.п.</strong><br>Точный тест Фишера: p ${comparison.p < .001 ? '&lt; 0,001' : '= ' + numberP(comparison.p)}. Без поправки на серию сравнений.</p>`;
  }
  $('quality').innerHTML = [
    ['Визиты: сервер / трекер', `${number(run.visits)} / ${c ? number(c.views) : '—'}`],
    ['Заявки: сервер / трекер', `${number(run.orders)} / ${c ? number(c.success) : '—'}`],
    ['Ошибки отправки / проверки', c ? `${c.errors} / ${c.invalid}` : '—'],
    ['Медиана ввода → успех', c?.median_input == null ? 'Нет данных' : `${number(c.median_input)} с`],
  ].map(([label,value])=>`<div class="quality-item"><span class="small-label">${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('');
  $('quality-notes').innerHTML = run.notes.length ? run.notes.map(n=>`<p class="notice">${esc(n)}</p>`).join('') : '<p class="notice neutral">Серверные счётчики совпадают с клиентскими. Проверенные прогоны — desktop; мобильная конверсия не измерялась.</p>';
  renderTable();
  const url = new URL(location.href);
  url.searchParams.set('run', selected); url.searchParams.set('control', control);
  history.replaceState(null, '', url);
}

function numberP(p) { return p.toFixed(3).replace('.', ','); }

function renderChart() {
  const left=44, right=1100, top=24, height=190, step=(right-left)/data.runs.length, maximum=.45;
  const y = value => top + height * (1-value/maximum);
  let svg = '<title>Серверная конверсия по экспериментам с 95% интервалами Уилсона</title>';
  for(let tick=0;tick<=.4;tick+=.1){svg+=`<line class="chart-grid" x1="${left}" y1="${y(tick)}" x2="1100" y2="${y(tick)}"/><text class="axis-text" x="${left-9}" y="${y(tick)+4}" text-anchor="end">${Math.round(tick*100)}%</text>`;}
  data.runs.forEach((r,i)=>{
    const x=left+i*step+step/2;
    const cls=r.id===selected?'bar-selected':r.id===control?'bar-control':'bar-neutral';
    const lo=y(r.interval[0]), hi=y(r.interval[1]);
    svg+=`<g><title>${esc(r.label)}: ${pct(r.rate)}, ${r.orders}/${r.visits}. Интервал ${pct(r.interval[0])}–${pct(r.interval[1])}</title><rect class="${cls}" x="${x-21}" y="${y(r.rate)}" width="42" height="${top+height-y(r.rate)}" rx="5"/><path class="whisker" d="M${x} ${hi}V${lo} M${x-5} ${hi}H${x+5} M${x-5} ${lo}H${x+5}"/><text class="chart-value" x="${x}" y="${hi-9}" text-anchor="middle">${pct(r.rate)}</text><text class="chart-label" x="${x}" y="238" text-anchor="middle">${esc(r.short)}</text><text class="axis-text" x="${x}" y="257" text-anchor="middle">n=${r.visits}</text></g>`;
  });
  $('history-chart').innerHTML=svg;
  $('run-chips').innerHTML=data.runs.map(r=>`<button class="chip${r.id===selected?' selected':''}${r.id===control?' control-chip':''}" data-run="${r.id}" aria-pressed="${r.id===selected}">${esc(r.short)}</button>`).join('');
}

function renderFunnel(run, base) {
  if (!run.client) {
    $('funnel').innerHTML='<div class="verdict"><strong>Нет клиентской телеметрии</strong><p>Для исходных 3% сохранился общий результат. Восстановить этапы воронки по нему нельзя.</p></div>';
    $('funnel-note').textContent='Отсутствующие данные не заменяются нулями.';
    return;
  }
  const c=run.client,b=base.client;
  const steps=[['views','Загрузка страницы'],['cta','Клик по CTA'],['field','CTA + показ первого поля'],['starts','CTA + начало ввода'],['success','Успешная отправка']];
  $('funnel').innerHTML=steps.map(([key,label],i)=>{
    const previous=i?c[steps[i-1][0]]:c.views;
    return `<div class="funnel-row"><div class="funnel-top"><span>${i+1}. ${label}</span><strong>${number(c[key])} <span class="small-label">/ ${number(c.views)}</span></strong></div><div class="funnel-track"><div class="funnel-fill" style="width:${100*c[key]/c.views}%"></div></div>${b?`<div class="funnel-control" title="Контроль: ${b[key]} из ${b.views}"><span style="width:${100*b[key]/b.views}%"></span></div>`:''}<div class="funnel-rate">${pct(rate(c[key],previous))} от предыдущего этапа${b?` · контроль: ${number(b[key])}`:''}</div></div>`;
  }).join('');
  $('funnel-note').textContent=`Это пересечения событий за визит, а не проверенная последовательность: показ поля мог быть до CTA. Зелёный — выбранный прогон, синий — контроль; ширина — доля от клиентских визитов своего прогона. Знаменатель здесь: ${c.views}; на сервере: ${run.visits}.`;
}

function renderTable() {
  const search=$('search').value.toLocaleLowerCase('ru');
  const runs=data.runs.filter(r=>`${r.label} ${r.commit}`.toLocaleLowerCase('ru').includes(search));
  $('run-table').innerHTML=runs.map(r=>`<tr class="${r.id===selected?'selected ':''}${r.id===control?'control-row':''}"><td><button class="table-run" data-run="${r.id}">${esc(r.label)}</button><small>${r.commit}${r.id===control?' · контроль':''}${r.id===selected?' · выбран':''}</small></td><td>${r.date.slice(8)}.${r.date.slice(5,7)}</td><td>${number(r.price)} ₽</td><td class="num">${number(r.visits)}</td><td class="num">${number(r.orders)}</td><td class="num"><span class="rate-badge">${pct(r.rate)}</span></td><td class="interval">${pct(r.interval[0])}–${pct(r.interval[1])}</td></tr>`).join('');
  $('table-empty').hidden=runs.length>0;
}

async function load() {
  $('refresh').disabled=true;
  $('status').className='status';
  $('status').textContent='Читаем локальные отчёты…';
  try {
    const response=await fetch('/api/data',{cache:'no-store'});
    if(!response.ok) throw new Error('Не удалось прочитать отчёты. Проверьте файлы и повторите загрузку.');
    data=await response.json();
    const params=new URLSearchParams(location.search);
    const valid=id=>data.runs.some(r=>r.id===id);
    selected=valid(selected)?selected:valid(params.get('run'))?params.get('run'):data.default_run;
    control=valid(control)?control:valid(params.get('control'))?params.get('control'):data.default_control;
    for(const id of ['run-select','control-select']){$(id).innerHTML=data.runs.map(r=>`<option value="${r.id}">${esc(r.label)} · ${pct(r.rate)}</option>`).join('');}
    render();
    $('dashboard').hidden=false;$('export').disabled=false;
    $('status').textContent=`${data.runs.length} основных прогонов из архива · последний: ${data.runs.at(-1).date.split('-').reverse().join('.')}`;
    $('updated').textContent=`Агрегаты прочитаны: ${new Date(data.generated_at).toLocaleString('ru-RU')}. Контакты и записи отдельных посетителей в панель не передаются.`;
    await loadDiagnostics();
  } catch(error) {
    $('status').className='status error';
    $('status').textContent=error.message + (data ? ' Показаны ранее загруженные данные.' : '');
  } finally { $('refresh').disabled=false; }
}

async function loadDiagnostics() {
  try {
    const response=await fetch('/api/diagnostics',{cache:'no-store'});
    if(!response.ok) throw new Error('Недоступен диагностический API. Перезапустите локальный сервер панели.');
    diagnosticExports=(await response.json()).exports;
    $('diagnostic-select').hidden=!diagnosticExports.length;
    $('diagnostic-select').innerHTML=diagnosticExports.map((r,i)=>`<option value="${i}">${esc(r.id)}</option>`).join('');
    if(diagnosticExports.length) $('diagnostic-select').value=String(diagnosticExports.length-1);
    renderDiagnostics();
  } catch(error) { $('diagnostic-content').innerHTML=`<p class="notice">${esc(error.message)}</p>`; }
}

function renderDiagnostics() {
  if(!diagnosticExports.length){
    $('diagnostic-content').innerHTML='<p class="notice">Диагностических прогонов пока нет. Исторические 363 отказа нельзя автоматически распределить по новым категориям. После нового прогона сюда попадут повторные показы, попытки взаимодействия и снимки доступности.</p>';
    return;
  }
  const d=diagnosticExports[Number($('diagnostic-select').value)];
  if(d.error){$('diagnostic-content').innerHTML=`<p class="notice">${esc(d.error)}</p>`;return;}
  const c=d.counts;
  const obstacleLabels={outside_view:'Видно меньше 50% первого поля',not_editable:'Поле не допускает редактирование или фокус',partly_covered:'Поле перекрыто в проверенных точках',pointer_disabled:'Нажатия на поле отключены'};
  const obstacleDetails=Object.entries(d.obstacles||{}).map(([key,count])=>`${esc(obstacleLabels[key]||key)}: ${number(count)}`).join(' · ');
  const branches=[['success_branch','Успешная заявка'],['input_without_success','Ввод без подтверждённого успеха'],['focus_without_input','Фокус без ввода'],['autofocus_without_input','Автофокус страницы без ввода и наблюдаемого взаимодействия'],['attempt_without_focus','Нажатие без фокуса'],['available_without_attempt','Поле доступно в последнем снимке, попыток нет'],['snapshot_obstacle','Первое поле недостаточно видно или недоступно, попыток нет'],['hidden_without_attempt','Скрытая вкладка в последнем снимке'],['unknown','Недостаточно данных / лимит / незавершённая попытка']];
  $('diagnostic-content').innerHTML=`<div class="quality-grid">${[['Визиты',d.visits],['CTA-попытки',c.attempts||0],['Попытки с возвратом к форме',c.returned||0],['Журналы с пропусками',d.sequence_gaps]].map(([label,n])=>`<div class="quality-item"><span class="small-label">${label}</span><strong>${number(n)}</strong></div>`).join('')}</div><div class="table-scroll"><table><thead><tr><th>Наблюдение по CTA-попытке</th><th class="num">Количество</th></tr></thead><tbody>${branches.map(([key,label])=>`<tr><td>${label}</td><td class="num">${number(c[key]||0)}</td></tr>`).join('')}</tbody></table></div><p class="notice neutral">Оценка относится к первому полю в последнем снимке после CTA. Ранее поле могло быть доступно. Это состояние интерфейса, а не установленная причина отказа. Автофокус страницы не означает начало заполнения.${obstacleDetails ? `<br>${obstacleDetails}. В одной попытке возможны несколько признаков.` : ''}</p><p class="footnote">Последнее прибытие → фокус: ${d.median_seconds.return_to_focus==null?'нет данных':number(d.median_seconds.return_to_focus)+' с'}; прибытие → уход без фокуса: ${d.median_seconds.return_to_exit==null?'нет данных':number(d.median_seconds.return_to_exit)+' с'}. Версия трекера: ${esc(d.versions.join(', '))}.</p>`;
}

$('run-select').addEventListener('change',e=>{selected=e.target.value;render();});
$('control-select').addEventListener('change',e=>{control=e.target.value;render();});
$('search').addEventListener('input',()=>{if(data)renderTable();});
$('refresh').addEventListener('click',load);
$('diagnostic-select').addEventListener('change',renderDiagnostics);
document.addEventListener('click',e=>{const button=e.target.closest('[data-run]');if(button){selected=button.dataset.run;render();}});
$('export').addEventListener('click',()=>{const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='landing-experiments.json';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);});
load();

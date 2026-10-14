import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { mkdirSync, writeFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const require = createRequire(resolve('wp-content/themes/razil/package.json'));
const { chromium } = require('playwright');
const dir = '_check/layout-2026-10-14';
mkdirSync(dir, { recursive: true });
const routes = [
  ['organization', '/uslugi/organizatsiya-pohoron/'],
  ['cremation', '/uslugi/krematsiya/'],
  ['transport', '/uslugi/transportirovka/'],
  ['landscaping', '/uslugi/blagoustroystvo/'],
  ['documents', '/uslugi/yuridicheskaya-pomoshch/'],
  ['contacts', '/kontakty/'], ['home', '/'],
];
const modes = [
  {width:320,height:740}, {width:375,height:812}, {width:768,height:1024},
  {width:1366,height:900}, {width:1920,height:1080},
  {width:1366,height:900,dark:true}, {width:1366,height:900,zoom:200},
];
const browser = await chromium.launch({channel:'chrome', headless:true});
const reports=[];
try {
  for (const [name, route] of routes) {
    for (const mode of modes) {
      const key=`${name}-${mode.width}-${mode.dark?'dark':'light'}-${mode.zoom||100}`;
      const page=await browser.newPage({viewport:{width:mode.width,height:mode.height},colorScheme:mode.dark?'dark':'light'});
      const errors=[]; page.on('pageerror', e=>errors.push(e.message));
      const response=await page.goto('http://ra-zil.test'+route,{waitUntil:'networkidle'});
      assert.equal(response.status(),200,key);
      await page.evaluate(()=>document.fonts.ready);
      if(mode.zoom) await page.evaluate(z=>document.documentElement.style.fontSize=z+'%',mode.zoom);
      if(mode.width===1366 || mode.width===375) {
        if(name==='home') await page.screenshot({path:`${dir}/${key}.png`});
        else if(name==='contacts') {
          await page.locator('.rz-map').scrollIntoViewIfNeeded();
          await page.waitForTimeout(2500);
          await page.locator('#office-directions').screenshot({path:`${dir}/${key}.png`});
        } else {
          await page.locator('.rz-svc-docs').screenshot({path:`${dir}/${key}-docs.png`});
          if(name==='organization') await page.locator('.rz-svc-timeline').screenshot({path:`${dir}/${key}-timeline.png`});
        }
      }
      const data=await page.evaluate(()=>{
        const rect=e=>e.getBoundingClientRect().toJSON();
        const hero=document.querySelector('.rz-hero');
        const list=document.querySelector('.rz-svc-timeline__list');
        const heads=[...document.querySelectorAll('.rz-svc-section-head')];
        const intro=document.querySelector('.rz-svc-intro-with-card');
        const photos=document.querySelector('.rz-contact-photos');
        const lead=document.querySelector('.rz-svc-stages__lead');
        return {
          overflow:document.documentElement.scrollWidth>innerWidth,
          clipped:[...document.querySelectorAll('.rz-svc-shell *, .rz-page-shell *, .rz-hero *')].filter(e=>{const r=rect(e);return r.width && (r.right>innerWidth+1 || r.left< -1);}).map(e=>e.className).slice(0,8),
          h1:document.querySelectorAll('h1').length,
          broken:[...document.images].filter(i=>i.complete&&!i.naturalWidth).map(i=>i.src),
          prices:[...document.querySelectorAll('.is-style-pricelist tbody td:last-child, .rz-svc-pack__price')].map(e=>e.textContent.trim()),
          docs:heads.map(e=>({r:rect(e),children:[...e.children].map(rect)})),
          intro:intro?[...intro.children].map(rect):null,
          timeline:list?{r:rect(list),dots:[...list.children].map(e=>{const p=getComputedStyle(e,'::before');const r=rect(e);return r.left+parseFloat(p.left)+parseFloat(p.width)/2;})}:null,
          lead:lead?{r:rect(lead),lineHeight:parseFloat(getComputedStyle(lead).lineHeight)}:null,
          contacts:photos?{photos:[...photos.children].map(rect),map:rect(document.querySelector('.rz-map iframe')),container:rect(document.querySelector('.rz-contact-directions'))}:null,
          hero:hero?{r:rect(hero),header:rect(document.querySelector('.rz-header')),src:hero.querySelector('img').currentSrc,scrollY}:null,
        };
      });
      assert.equal(data.overflow,false,key);assert.deepEqual(data.clipped,[],key);
      assert.equal(data.h1,1,key);assert.deepEqual(data.broken,[],key);assert.deepEqual(errors,[],key);
      if(data.prices.length) assert.ok(data.prices.every(p=>p==='…'||p==='от …'),key);
      for(const d of data.docs) for(const child of d.children) assert.ok(Math.abs(child.width-d.r.width)<2,key+' document width');
      if(data.intro && mode.width>=1366 && !mode.zoom) assert.ok(Math.abs((data.intro[0].top+data.intro[0].bottom-data.intro[1].top-data.intro[1].bottom)/2)<1,key+' card centre');
      if(data.timeline && mode.width>=1366 && !mode.zoom){const {r,dots}=data.timeline;assert.ok(Math.abs(dots[0]-r.left-(r.right-dots.at(-1)))<1,key+' timeline centre');}
      if(name==='organization' && mode.width>=1366 && !mode.zoom) assert.ok(data.lead.r.height<=data.lead.lineHeight+1,key+' single-line lead');
      if(data.contacts){assert.ok(Math.abs(data.contacts.map.width-data.contacts.container.width)<2,key+' map width');if(mode.width>=768&&!mode.zoom) assert.ok(Math.abs(data.contacts.photos[0].top-data.contacts.photos[1].top)<1,key+' photo row');assert.ok(data.contacts.map.top>=Math.max(...data.contacts.photos.map(r=>r.bottom)),key+' map below photos');}
      if(data.hero){const h=data.hero;assert.ok(Math.abs(h.r.top+h.scrollY-h.header.bottom)<2,key+' no gap');assert.ok(h.r.height>=mode.height-h.header.height-1,key+' full-screen');assert.ok(h.src.includes('kazarmenny-glavnyy-ekran'),key+' new photo');}
      reports.push({key,...data,errors});
      writeFileSync(`${dir}/results.json`,JSON.stringify(reports,null,2));
      console.log(key+' OK');
      await page.close();
    }
  }
} finally {await browser.close();}

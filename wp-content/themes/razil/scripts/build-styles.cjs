const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const cssnano = require('cssnano');
(async () => {
  for (const name of ['main', 'information', 'service-details']) {
    const source = path.join(__dirname, '../assets/css', name + '.css');
    const target = path.join(__dirname, '../assets/css', name + '.min.css');
    const result = await postcss([cssnano({ preset: 'default' })]).process(fs.readFileSync(source, 'utf8'), { from: source, to: target, map: false });
    fs.writeFileSync(target, result.css);
    console.log(name + ': ' + fs.statSync(source).size + ' -> ' + Buffer.byteLength(result.css) + ' bytes');
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });

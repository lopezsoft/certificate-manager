const fs = require('fs');
const path = require('path');
const walkSync = (dir, filelist = []) => {
  fs.readdirSync(dir).forEach(file => {
    filelist = fs.statSync(path.join(dir, file)).isDirectory()
      ? walkSync(path.join(dir, file), filelist)
      : filelist.concat(path.join(dir, file));
  });
  return filelist;
};
const files = walkSync('../assets/scss').filter(f => f.endsWith('.scss'));
let vars = new Set();
files.forEach(f => {
  const content = fs.readFileSync(f, 'utf8');
  const matches = content.match(/\$theme-[a-zA-Z0-9_-]+/g);
  if (matches) {
    matches.forEach(m => vars.add(m));
  }
});
let scss = '';
vars.forEach(v => {
  scss += v + ': #ccc !default;\n';
});
fs.writeFileSync('src/css/theme-vars.scss', scss);
console.log('Extracted ' + vars.size + ' variables');

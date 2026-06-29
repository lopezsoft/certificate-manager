const fs = require('fs');
const file = '../api-docs.json';
const data = JSON.parse(fs.readFileSync(file, 'utf8'));

if (data.info && data.info.description) {
  data.info.description = "";
}

fs.writeFileSync(file, JSON.stringify(data, null, 2), 'utf8');
console.log("Descripción de info vaciada exitosamente.");

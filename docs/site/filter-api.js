const fs = require('fs');

const file = '../api-docs.json';
const data = JSON.parse(fs.readFileSync(file, 'utf8'));

const adminKeywords = ['admin'];
const excludedTags = ['Analíticas IA', 'Cupos Admin'];

// Clean paths
const pathsToRemove = [];
Object.entries(data.paths).forEach(([path, methods]) => {
  const methodsToRemove = [];
  Object.entries(methods).forEach(([method, op]) => {
    // We only process valid HTTP methods
    if(!['get','post','put','patch','delete'].includes(method.toLowerCase())) return;
    
    let shouldRemove = false;
    
    // Check tags
    if (op.tags && op.tags.some(t => excludedTags.includes(t))) {
      shouldRemove = true;
    }
    
    // Check path for 'admin'
    if (path.toLowerCase().includes('admin')) {
      shouldRemove = true;
    }
    
    // Check summary for 'admin'
    if (op.summary && op.summary.toLowerCase().includes('admin')) {
      shouldRemove = true;
    }
    
    if (shouldRemove) {
      methodsToRemove.push(method);
    }
  });
  
  methodsToRemove.forEach(m => {
    delete data.paths[path][m];
  });
  
  // If no methods left (except maybe params/servers), we check if there are actual HTTP methods left
  const hasMethods = Object.keys(data.paths[path]).some(m => ['get','post','put','patch','delete'].includes(m.toLowerCase()));
  if (!hasMethods) {
    pathsToRemove.push(path);
  }
});

pathsToRemove.forEach(p => {
  delete data.paths[p];
});

// Clean global tags if they exist
if (data.tags) {
  data.tags = data.tags.filter(t => !excludedTags.includes(t.name));
}

fs.writeFileSync(file, JSON.stringify(data, null, 2), 'utf8');
console.log('Filtrado completado con exito.');

const fs = require('fs');
const file = '../api-docs.json';
const data = JSON.parse(fs.readFileSync(file, 'utf8'));

if (data.info && data.info.description) {
  data.info.description = "API REST unificada (v1) para la gestión completa de solicitudes de certificados digitales.\n\nEsta API provee los mecanismos necesarios para integrar la emisión, firma y gestión del ciclo de vida de los certificados digitales de manera segura y eficiente.\n\n**Autenticación:** El sistema utiliza OAuth 2.0 (Bearer Token). Los tokens de acceso se pueden obtener mediante el flujo de credenciales estandarizadas. Adicionalmente, para integraciones automatizadas (Machine-to-Machine) se cuenta con la posibilidad de generar Personal Access Tokens (PAT).";
}

fs.writeFileSync(file, JSON.stringify(data, null, 2), 'utf8');
console.log("Descripción sanitizada exitosamente.");

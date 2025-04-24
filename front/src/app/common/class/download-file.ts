

export  class DownloadFile {
  public static Xml(base64String: string, fileName: string = 'archivo.xml') {
    this.downloadFile(base64String, fileName, 'application/xml');
  }
  
  public static Pdf(base64String: string, fileName: string = 'archivo.pdf') {
    // Convertir base64 a Blob
    this.downloadFile(base64String, fileName, 'application/pdf');
  }
  
  public static base64ToBlob(base64: string, contentType: string) {
    const byteCharacters = atob(base64);
    const byteArrays = [];
    
    for (let offset = 0; offset < byteCharacters.length; offset += 512) {
      const slice = byteCharacters.slice(offset, offset + 512);
      
      const byteNumbers = new Array(slice.length);
      for (let i = 0; i < slice.length; i++) {
        byteNumbers[i] = slice.charCodeAt(i);
      }
      
      const byteArray = new Uint8Array(byteNumbers);
      byteArrays.push(byteArray);
    }
    
    return new Blob(byteArrays, { type: contentType });
  }
  
  private static downloadFile(base64String: string, fileName: string, contentType: string) {
    // Convertir base64 a Blob
    const pdfBlob = this.base64ToBlob(base64String, contentType);
    
    // Crear un URL para el Blob
    const pdfUrl = URL.createObjectURL(pdfBlob);
    
    // Crear un elemento <a> temporal para descargar el archivo
    const downloadLink = document.createElement('a');
    downloadLink.href = pdfUrl;
    downloadLink.setAttribute('download', fileName); // El nombre de tu archivo PDF
    
    // Simular un clic en el enlace
    document.body.appendChild(downloadLink);
    downloadLink.click();
    
    // Limpiar / remover el enlace del DOM
    document.body.removeChild(downloadLink);
  }
}

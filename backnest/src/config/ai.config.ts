import { registerAs } from '@nestjs/config';

export default registerAs('ai', () => ({
  ocrService: process.env.AI_OCR_SERVICE ?? 'textract',
  awsTextract: {
    region: process.env.AWS_TEXTRACT_REGION ?? 'us-east-1',
    accessKeyId: process.env.AWS_ACCESS_KEY_ID ?? '',
    secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY ?? '',
  },
  googleVision: {
    apiKey: process.env.GOOGLE_VISION_API_KEY ?? '',
    projectId: process.env.GOOGLE_CLOUD_PROJECT_ID ?? '',
  },
  gemini: {
    apiKey: process.env.GEMINI_API_KEY ?? '',
    model: process.env.GEMINI_MODEL ?? 'gemini-1.5-flash',
  },
  processing: {
    maxFileSize: 10 * 1024 * 1024, // 10 MB
    supportedFormats: ['jpg', 'jpeg', 'png', 'pdf'],
    timeout: 30,
  },
}));

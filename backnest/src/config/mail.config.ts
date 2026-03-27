import { registerAs } from '@nestjs/config';

export default registerAs('mail', () => ({
  host: process.env.MAIL_HOST ?? 'smtp.mailtrap.io',
  port: parseInt(process.env.MAIL_PORT ?? '587', 10),
  user: process.env.MAIL_USER ?? '',
  pass: process.env.MAIL_PASS ?? '',
  from: {
    address: process.env.MAIL_FROM_ADDRESS ?? 'noreply@certificate-manager.com',
    name: process.env.MAIL_FROM_NAME ?? 'Certificate Manager',
  },
}));

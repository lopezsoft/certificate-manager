import { registerAs } from '@nestjs/config';

export default registerAs('mail', () => ({
  transport: (process.env.MAIL_TRANSPORT ?? 'smtp').toLowerCase(),
  host: process.env.MAIL_HOST ?? 'smtp.mailtrap.io',
  port: parseInt(process.env.MAIL_PORT ?? '587', 10),
  user: process.env.MAIL_USER ?? '',
  pass: process.env.MAIL_PASS ?? '',
  from: {
    address: process.env.MAIL_FROM_ADDRESS ?? 'noreply@certificate-manager.com',
    name: process.env.MAIL_FROM_NAME ?? 'Certificate Manager',
  },
  ses: {
    region: process.env.AWS_SES_REGION ?? process.env.AWS_REGION ?? 'us-east-1',
    accessKeyId: process.env.AWS_ACCESS_KEY_ID ?? '',
    secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY ?? '',
    sessionToken: process.env.AWS_SESSION_TOKEN ?? '',
    fromArn: process.env.AWS_SES_FROM_ARN ?? '',
    configurationSet: process.env.AWS_SES_CONFIGURATION_SET ?? '',
  },
}));

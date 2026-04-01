import { Module } from '@nestjs/common';
import { MailerModule } from '@nestjs-modules/mailer';
import { HandlebarsAdapter } from '@nestjs-modules/mailer/dist/adapters/handlebars.adapter';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { SESv2Client, SendEmailCommand } from '@aws-sdk/client-sesv2';
import * as path from 'path';
import { MailService } from './mail.service';

@Module({
  imports: [
    MailerModule.forRootAsync({
      imports: [ConfigModule],
      useFactory: (configService: ConfigService) => {
        const fromName = configService.get<string>('mail.from.name', 'Certificate Manager');
        const fromAddress = configService.get<string>('mail.from.address', 'noreply@certificate-manager.com');
        const transportType = configService.get<string>('mail.transport', 'smtp');

        const baseConfig = {
          defaults: {
            from: `"${fromName}" <${fromAddress}>`,
          },
          template: {
            dir: path.join(process.cwd(), 'src', 'modules', 'mail', 'templates'),
            adapter: new HandlebarsAdapter(),
            options: {
              strict: true,
            },
          },
        };

        if (transportType === 'ses') {
          const accessKeyId = configService.get<string>('mail.ses.accessKeyId');
          const secretAccessKey = configService.get<string>('mail.ses.secretAccessKey');
          const sessionToken = configService.get<string>('mail.ses.sessionToken');

          const sesClient = new SESv2Client({
            region: configService.get<string>('mail.ses.region', 'us-east-1'),
            credentials:
              accessKeyId && secretAccessKey
                ? {
                  accessKeyId,
                  secretAccessKey,
                  sessionToken: sessionToken || undefined,
                }
                : undefined,
          });

          return {
            ...baseConfig,
            transport: {
              SES: {
                sesClient,
                SendEmailCommand,
              },
            },
          };
        }

        return {
          ...baseConfig,
          transport: {
            host: configService.get<string>('mail.host', 'smtp.mailtrap.io'),
            port: configService.get<number>('mail.port', 587),
            secure: false,
            auth: {
              user: configService.get<string>('mail.user'),
              pass: configService.get<string>('mail.pass'),
            },
          },
        };
      },
      inject: [ConfigService],
    }),
  ],
  providers: [MailService],
  exports: [MailService],
})
export class MailModule { }

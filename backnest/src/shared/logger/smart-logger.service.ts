import { Injectable, LoggerService } from '@nestjs/common';

type LogLevel = 'log' | 'error' | 'warn' | 'debug' | 'verbose';

@Injectable()
export class SmartLoggerService implements LoggerService {
  private formatMessage(
    level: LogLevel,
    message: string,
    context?: string,
  ): string {
    const ts = new Date().toISOString();
    const ctx = context ? `[${context}]` : '';
    return `${ts} [${level.toUpperCase()}] ${ctx} ${message}`;
  }

  log(message: string, context?: string): void {
    process.stdout.write(this.formatMessage('log', message, context) + '\n');
  }

  error(message: string, trace?: string, context?: string): void {
    process.stderr.write(
      this.formatMessage('error', message, context) +
      (trace ? `\n${trace}` : '') +
      '\n',
    );
  }

  warn(message: string, context?: string): void {
    process.stdout.write(this.formatMessage('warn', message, context) + '\n');
  }

  debug(message: string, context?: string): void {
    if (process.env.NODE_ENV !== 'production') {
      process.stdout.write(
        this.formatMessage('debug', message, context) + '\n',
      );
    }
  }

  verbose(message: string, context?: string): void {
    if (process.env.NODE_ENV !== 'production') {
      process.stdout.write(
        this.formatMessage('verbose', message, context) + '\n',
      );
    }
  }
}

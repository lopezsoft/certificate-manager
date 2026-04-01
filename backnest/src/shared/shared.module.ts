import { Global, Module } from '@nestjs/common';
import { SmartLoggerService } from './logger/smart-logger.service';

/**
 * SharedModule exporta utilidades globales disponibles en toda la aplicación.
 * Al ser @Global, no necesita importarse en cada módulo.
 */
@Global()
@Module({
  providers: [SmartLoggerService],
  exports: [SmartLoggerService],
})
export class SharedModule { }

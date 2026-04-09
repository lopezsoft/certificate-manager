import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { WebhookEndpoint } from '@database/entities/webhook-endpoint.entity';
import { WebhookDelivery } from '@database/entities/webhook-delivery.entity';
import { WebhooksController } from '@modules/webhooks/webhooks.controller';
import { WebhooksService } from '@modules/webhooks/webhooks.service';

@Module({
  imports: [TypeOrmModule.forFeature([WebhookEndpoint, WebhookDelivery])],
  controllers: [WebhooksController],
  providers: [WebhooksService],
  exports: [WebhooksService],
})
export class WebhooksModule { }

import { PartialType } from '@nestjs/swagger';
import { CreateWebhookEndpointDto } from '@modules/webhooks/dto/create-webhook-endpoint.dto';

export class UpdateWebhookEndpointDto extends PartialType(
  CreateWebhookEndpointDto,
) { }

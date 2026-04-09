import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { WebhookEndpoint } from './webhook-endpoint.entity';

export enum WebhookDeliveryStatus {
  PENDING = 'pending',
  DELIVERED = 'delivered',
  FAILED = 'failed',
}

@Entity('webhook_deliveries')
export class WebhookDelivery {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'webhook_endpoint_id' })
  webhookEndpointId: number;

  @Column({ name: 'event_type', length: 100 })
  eventType: string;

  @Column({ type: 'json', nullable: true })
  payload: Record<string, any>;

  @Column({ length: 255, nullable: true })
  signature: string;

  @Column({ name: 'http_status', nullable: true })
  httpStatus: number;

  @Column({ name: 'response_body', type: 'text', nullable: true })
  responseBody: string;

  @Column({
    type: 'enum',
    enum: WebhookDeliveryStatus,
    default: WebhookDeliveryStatus.PENDING,
  })
  status: WebhookDeliveryStatus;

  @Column({ default: 1 })
  attempt: number;

  @Column({ name: 'delivered_at', type: 'timestamp', nullable: true })
  deliveredAt: Date;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @ManyToOne(() => WebhookEndpoint, (e) => e.deliveries, { nullable: true })
  @JoinColumn({ name: 'webhook_endpoint_id' })
  webhookEndpoint: WebhookEndpoint;
}

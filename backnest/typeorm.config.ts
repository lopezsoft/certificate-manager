import { DataSource } from 'typeorm';
import { config } from 'dotenv';
import { join } from 'path';

config(); // Load .env

export default new DataSource({
  type: 'mariadb',
  host: process.env.DB_HOST || '127.0.0.1',
  port: parseInt(process.env.DB_PORT || '3307', 10),
  username: process.env.DB_USERNAME || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_DATABASE || 'cm_test',
  entities: [join(__dirname, 'src/database/entities/**/*.entity.{ts,js}')],
  migrations: [join(__dirname, 'src/database/migrations/**/*.{ts,js}')],
  synchronize: false,
  logging: process.env.NODE_ENV !== 'production',
});

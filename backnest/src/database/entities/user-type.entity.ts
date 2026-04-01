import { Column, Entity, PrimaryGeneratedColumn } from 'typeorm';

@Entity('user_types')
export class UserType {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'user_type_name', length: 100 })
  userTypeName: string;

  @Column({ length: 50, nullable: true })
  type: string;

  @Column({ default: 1 })
  active: number;
}

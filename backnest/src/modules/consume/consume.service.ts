import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { CertificateRequest } from '@database/entities/certificate-request.entity';

@Injectable()
export class ConsumeService {
  constructor(
    @InjectRepository(CertificateRequest)
    private readonly certRepo: Repository<CertificateRequest>,
  ) { }

  async readByYear(year: number) {
    const start = new Date(year, 0, 1, 0, 0, 0);
    const end = new Date(year, 11, 31, 23, 59, 59);

    const items = await this.certRepo
      .createQueryBuilder('cr')
      .where('cr.created_at BETWEEN :start AND :end', { start, end })
      .orderBy('cr.created_at', 'DESC')
      .getMany();

    return {
      year,
      total: items.length,
      items,
    };
  }

  async readByMonth(year: number, month: number) {
    const start = new Date(year, month - 1, 1, 0, 0, 0);
    const end = new Date(year, month, 0, 23, 59, 59);

    const items = await this.certRepo
      .createQueryBuilder('cr')
      .where('cr.created_at BETWEEN :start AND :end', { start, end })
      .orderBy('cr.created_at', 'DESC')
      .getMany();

    return {
      year,
      month,
      total: items.length,
      items,
    };
  }
}

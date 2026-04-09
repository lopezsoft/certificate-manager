import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { DocumentAnalysisResult } from '@database/entities/document-analysis-result.entity';
import { FileManager } from '@database/entities/file-manager.entity';
import { AiController } from '@modules/ai/ai.controller';
import { AiService } from '@modules/ai/ai.service';

@Module({
  imports: [TypeOrmModule.forFeature([DocumentAnalysisResult, FileManager])],
  controllers: [AiController],
  providers: [AiService],
  exports: [AiService],
})
export class AiModule { }

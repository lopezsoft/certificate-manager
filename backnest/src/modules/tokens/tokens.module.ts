import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { PersonalAccessToken } from '@database/entities/personal-access-token.entity';
import { TokensController } from '@modules/tokens/tokens.controller';
import { TokensService } from '@modules/tokens/tokens.service';

@Module({
  imports: [TypeOrmModule.forFeature([PersonalAccessToken])],
  controllers: [TokensController],
  providers: [TokensService],
  exports: [TokensService],
})
export class TokensModule { }

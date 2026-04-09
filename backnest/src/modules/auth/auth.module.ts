import { Module } from '@nestjs/common';
import { PassportModule } from '@nestjs/passport';
import { TypeOrmModule } from '@nestjs/typeorm';
import { User } from '@database/entities/user.entity';
import { UserType } from '@database/entities/user-type.entity';
import { PasswordReset } from '@database/entities/password-reset.entity';
import { OAuthAccessToken } from '@database/entities/oauth-access-token.entity';
import { AccessUsers } from '@database/entities/access-users.entity';
import { BusinessUser } from '@database/entities/business-user.entity';
import { Company } from '@database/entities/company.entity';
import { AuthController } from '@modules/auth/auth.controller';
import { AuthService } from '@modules/auth/auth.service';
import { BearerTokenStrategy } from '@modules/auth/strategies/bearer-token.strategy';
import { JwtAuthGuard } from '@modules/auth/guards/jwt-auth.guard';
import { RolesGuard } from '@modules/auth/guards/roles.guard';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      User,
      UserType,
      PasswordReset,
      OAuthAccessToken,
      AccessUsers,
      BusinessUser,
      Company,
    ]),
    PassportModule.register({ defaultStrategy: 'jwt' }),
  ],
  controllers: [AuthController],
  providers: [AuthService, BearerTokenStrategy, JwtAuthGuard, RolesGuard],
  exports: [JwtAuthGuard, RolesGuard, PassportModule],
})
export class AuthModule { }

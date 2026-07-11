import { Body, Controller, HttpCode, Ip, Post, Headers } from "@nestjs/common";
import { ApiOperation, ApiTags } from "@nestjs/swagger";
import { IsEmail, IsOptional, IsString, MinLength } from "class-validator";
import { Public } from "./decorators/public.decorator";
import { AuthService } from "./auth.service";

class LoginDto {
  @IsEmail()
  email: string;

  @IsString()
  @MinLength(8)
  password: string;

  /** Código TOTP — obrigatório apenas quando o usuário tem MFA ativo. */
  @IsOptional()
  @IsString()
  mfaCode?: string;
}

class RefreshDto {
  @IsString()
  refreshToken: string;
}

@ApiTags("auth")
@Controller({ path: "auth", version: "1" })
export class AuthController {
  constructor(private readonly auth: AuthService) {}

  @Public()
  @Post("login")
  @HttpCode(200)
  @ApiOperation({ summary: "Autentica com e-mail/senha (+ MFA) e emite tokens" })
  login(@Body() dto: LoginDto, @Ip() ip: string, @Headers("user-agent") userAgent?: string) {
    return this.auth.login(dto.email, dto.password, dto.mfaCode, { ip, userAgent });
  }

  @Public()
  @Post("refresh")
  @HttpCode(200)
  @ApiOperation({ summary: "Rotaciona o refresh token e emite novo access token" })
  refresh(@Body() dto: RefreshDto) {
    return this.auth.refresh(dto.refreshToken);
  }

  @Public()
  @Post("logout")
  @HttpCode(204)
  @ApiOperation({ summary: "Revoga a sessão do refresh token" })
  async logout(@Body() dto: RefreshDto) {
    await this.auth.logout(dto.refreshToken);
  }
}

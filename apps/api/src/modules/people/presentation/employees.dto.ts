import { ApiProperty, ApiPropertyOptional, PartialType } from "@nestjs/swagger";
import { IsDateString, IsOptional, IsString, IsUUID } from "class-validator";

export class CreateEmployeeDto {
  @ApiProperty()
  @IsUUID()
  companyId: string;

  @ApiProperty({ description: "Matrícula única na empresa" })
  @IsString()
  registrationNumber: string;

  @ApiProperty()
  @IsString()
  fullName: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsUUID()
  departmentId?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsUUID()
  positionId?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsUUID()
  managerId?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsDateString()
  hiredAt?: string;
}

export class UpdateEmployeeDto extends PartialType(CreateEmployeeDto) {}

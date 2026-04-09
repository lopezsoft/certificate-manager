import { Controller, Get } from '@nestjs/common';
import { ApiOkResponse, ApiOperation, ApiTags } from '@nestjs/swagger';
import { MasterService } from '@modules/master/master.service';

@ApiTags('Master')
@Controller()
export class MasterController {
  constructor(private readonly masterService: MasterService) { }

  /** GET /api/v1/identity-documents */
  @Get('identity-documents')
  @ApiOperation({ summary: 'Tipos de documentos de identidad' })
  @ApiOkResponse({ description: 'Listado de documentos de identidad' })
  async identityDocuments() {
    const data = await this.masterService.getIdentityDocuments();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/organization-type */
  @Get('organization-type')
  @ApiOperation({ summary: 'Tipos de organización' })
  @ApiOkResponse({ description: 'Listado de tipos de organización' })
  async typeOrganizations() {
    const data = await this.masterService.getTypeOrganizations();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/master/user-types */
  @Get('user-types')
  @ApiOperation({ summary: 'Tipos de usuario' })
  @ApiOkResponse({ description: 'Listado de tipos de usuario' })
  async userTypes() {
    const data = await this.masterService.getUserTypes();
    return { dataRecords: { data } };
  }

  /** GET /api/v1/master/languages */
  @Get('languages')
  @ApiOperation({ summary: 'Idiomas disponibles' })
  @ApiOkResponse({ description: 'Listado de idiomas' })
  async languages() {
    const data = await this.masterService.getLanguages();
    return { dataRecords: { data } };
  }
}

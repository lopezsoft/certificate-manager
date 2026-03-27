import { AuthController } from '@modules/auth/auth.controller';
import { LocationsController } from '@modules/locations/locations.controller';
import { CrudController } from '@modules/crud/crud.controller';

describe('Paridad controladores (mock-only, sin DB)', () => {
  it('AuthController.email/verification-notification debe usar email público como Laravel', async () => {
    const authService = {
      sendEmailVerificationNotificationByEmail: jest
        .fn()
        .mockResolvedValue({ message: 'Se ha enviado un correo electrónico de verificación' }),
    } as any;

    const controller = new AuthController(authService);
    const result = await controller.resendVerification({
      email: 'demo@example.com',
    } as any);

    expect(authService.sendEmailVerificationNotificationByEmail).toHaveBeenCalledWith(
      'demo@example.com',
    );
    expect(result).toEqual({
      message: 'Se ha enviado un correo electrónico de verificación',
    });
  });

  it('LocationsController debe respetar query/code opcionales como Laravel', async () => {
    const locationsService = {
      getCountries: jest.fn().mockResolvedValue([]),
      getDepartments: jest.fn().mockResolvedValue([]),
      getCities: jest.fn().mockResolvedValue([{ id: 1 }]),
      getPostalCodesByCity: jest.fn().mockResolvedValue([]),
    } as any;

    const controller = new LocationsController(locationsService);
    const result = await controller.cities('med', '05001');

    expect(locationsService.getCities).toHaveBeenCalledWith('med', '05001');
    expect(result).toEqual({ dataRecords: { data: [{ id: 1 }] } });
  });

  it('CrudController debe conservar contrato apiResource /crud (read/create/show/update/destroy)', async () => {
    const crudService = {
      getSettings: jest.fn(),
      getCompanySettings: jest.fn(),
      getReportHeader: jest.fn(),
      upsertReportHeader: jest.fn(),
      crudRead: jest
        .fn()
        .mockResolvedValue({ __paginated: true, items: [], meta: { currentPage: 1, totalPages: 1, itemsPerPage: 15, totalItems: 0 } }),
      crudCreate: jest.fn().mockResolvedValue([{ id: 1 }]),
      crudUpdate: jest.fn().mockResolvedValue(undefined),
      crudDelete: jest.fn().mockResolvedValue([{ id: 1 }]),
    } as any;

    const controller = new CrudController(crudService);
    const user = { companyId: 99 } as any;

    await controller.crudIndex({ tbPrefix: 'T001' }, user);
    expect(crudService.crudRead).toHaveBeenCalledWith({ tbPrefix: 'T001' }, null, 99);

    const createRes = await controller.crudStore(
      { tbPrefix: 'T001', records: { company_name: 'ACME' } },
      user,
    );
    expect(crudService.crudCreate).toHaveBeenCalledWith(
      { tbPrefix: 'T001', records: { company_name: 'ACME' } },
      99,
    );
    expect(createRes).toEqual({
      message: 'Registro creado correctamente.',
      dataRecords: { data: [{ id: 1 }] },
    });

    await controller.crudShow(1, { tbPrefix: 'T001' }, user);
    expect(crudService.crudRead).toHaveBeenCalledWith({ tbPrefix: 'T001' }, 1, 99);

    const updateRes = await controller.crudUpdate(
      1,
      { tbPrefix: 'T001', records: { id: 1, company_name: 'NEW' } },
      user,
    );
    expect(crudService.crudUpdate).toHaveBeenCalledWith(
      1,
      { tbPrefix: 'T001', records: { id: 1, company_name: 'NEW' } },
      99,
    );
    expect(updateRes).toEqual({ message: 'Registro actualizado correctamente.' });

    const deleteRes = await controller.crudDestroy(1, { tbPrefix: 'T001' }, user);
    expect(crudService.crudDelete).toHaveBeenCalledWith(1, { tbPrefix: 'T001' }, 99);
    expect(deleteRes).toEqual({ dataRecords: { data: [{ id: 1 }] } });
  });
});

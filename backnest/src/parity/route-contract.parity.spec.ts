import 'reflect-metadata';
import { RequestMethod, Type } from '@nestjs/common';
import {
  GUARDS_METADATA,
  METHOD_METADATA,
  PATH_METADATA,
} from '@nestjs/common/constants';

import { AuthController } from '@modules/auth/auth.controller';
import { LocationsController } from '@modules/locations/locations.controller';
import { MasterController } from '@modules/master/master.controller';
import { CertificatesController } from '@modules/certificates/certificates.controller';
import { FileManagerController } from '@modules/files/file-manager.controller';
import { CompaniesController } from '@modules/companies/companies.controller';
import { CrudController } from '@modules/crud/crud.controller';
import { ConsumeController } from '@modules/consume/consume.controller';
import { TokensController } from '@modules/tokens/tokens.controller';
import { NotificationsController } from '@modules/notifications/notifications.controller';
import { WebhooksController } from '@modules/webhooks/webhooks.controller';

interface RouteInfo {
  method: string;
  path: string;
  guarded: boolean;
}

const VERB_MAP: Record<number, string> = {
  [RequestMethod.GET]: 'GET',
  [RequestMethod.POST]: 'POST',
  [RequestMethod.PUT]: 'PUT',
  [RequestMethod.DELETE]: 'DELETE',
  [RequestMethod.PATCH]: 'PATCH',
  [RequestMethod.OPTIONS]: 'OPTIONS',
  [RequestMethod.HEAD]: 'HEAD',
  [RequestMethod.ALL]: 'ALL',
};

function normalizePath(path: string): string {
  return path
    .replace(/^\/+/, '')
    .replace(/\/+$/, '')
    .replace(/\/+/g, '/');
}

function toLaravelPath(path: string): string {
  return `/api/v1/${normalizePath(path)}`;
}

function extractRoutes(controller: Type<unknown>): RouteInfo[] {
  const basePathRaw = Reflect.getMetadata(PATH_METADATA, controller) ?? '';
  const basePath = normalizePath(String(basePathRaw));
  const classGuards = Reflect.getMetadata(GUARDS_METADATA, controller) ?? [];

  const proto = controller.prototype;
  const methods = Object.getOwnPropertyNames(proto).filter(
    (m) => m !== 'constructor' && typeof proto[m] === 'function',
  );

  const routes: RouteInfo[] = [];

  for (const methodName of methods) {
    const handler = proto[methodName];
    const routePathMeta = Reflect.getMetadata(PATH_METADATA, handler);
    const methodMeta = Reflect.getMetadata(METHOD_METADATA, handler);

    if (routePathMeta === undefined || methodMeta === undefined) {
      continue;
    }

    const methodGuards = Reflect.getMetadata(GUARDS_METADATA, handler) ?? [];
    const guarded = classGuards.length > 0 || methodGuards.length > 0;

    const method = VERB_MAP[methodMeta] ?? `UNKNOWN_${String(methodMeta)}`;
    const methodPaths = Array.isArray(routePathMeta) ? routePathMeta : [routePathMeta];

    for (const p of methodPaths) {
      const fullPath = [basePath, normalizePath(String(p))]
        .filter(Boolean)
        .join('/');

      routes.push({ method, path: toLaravelPath(fullPath), guarded });
    }
  }

  return routes;
}

function asKey(method: string, path: string): string {
  return `${method} ${path}`;
}

describe('Paridad Laravel - Contrato de rutas y seguridad (sin DB)', () => {
  const controllers: Array<Type<unknown>> = [
    AuthController,
    LocationsController,
    MasterController,
    CertificatesController,
    FileManagerController,
    CompaniesController,
    CrudController,
    ConsumeController,
    TokensController,
    NotificationsController,
    WebhooksController,
  ];

  const routeMap = new Map<string, RouteInfo>();

  beforeAll(() => {
    for (const controller of controllers) {
      for (const route of extractRoutes(controller)) {
        routeMap.set(asKey(route.method, route.path), route);
      }
    }
  });

  const expectedPublic: Array<[string, string]> = [
    ['GET', '/api/v1/countries'],
    ['GET', '/api/v1/departments'],
    ['GET', '/api/v1/cities'],
    ['GET', '/api/v1/identity-documents'],
    ['GET', '/api/v1/organization-type'],
    ['POST', '/api/v1/auth/login'],
    ['POST', '/api/v1/register'],
    ['POST', '/api/v1/forgot-password'],
    ['POST', '/api/v1/reset-password'],
    ['GET', '/api/v1/verify-email/:id/:hash'],
    ['POST', '/api/v1/email/verification-notification'],
  ];

  const expectedProtected: Array<[string, string]> = [
    ['GET', '/api/v1/auth/logout'],
    ['GET', '/api/v1/auth/user'],
    ['GET', '/api/v1/profile'],
    ['GET', '/api/v1/profile/types'],
    ['PUT', '/api/v1/profile/:id'],

    ['GET', '/api/v1/crud'],
    ['POST', '/api/v1/crud'],
    ['GET', '/api/v1/crud/:id'],
    ['PUT', '/api/v1/crud/:id'],
    ['DELETE', '/api/v1/crud/:id'],

    ['GET', '/api/v1/consume/:year'],
    ['GET', '/api/v1/consume/:year/:month'],

    ['POST', '/api/v1/certificate-request'],
    ['POST', '/api/v1/certificate-request/:id/send-mail'],
    ['GET', '/api/v1/certificate-request'],
    ['GET', '/api/v1/certificate-request/all'],
    ['GET', '/api/v1/certificate-request/:id'],
    ['PUT', '/api/v1/certificate-request/:id'],
    ['PUT', '/api/v1/certificate-request/:id/status'],
    ['DELETE', '/api/v1/certificate-request/:id'],

    ['POST', '/api/v1/certificate-request/:certificateRequestId/files'],
    ['DELETE', '/api/v1/certificate-request/:certificateRequestId/files/:fileId'],

    ['GET', '/api/v1/company'],
    ['GET', '/api/v1/company/settings'],
    ['PUT', '/api/v1/company/settings'],

    ['GET', '/api/v1/settings/reports'],
    ['PUT', '/api/v1/settings/reports/:id'],

    ['GET', '/api/v1/tokens'],
    ['POST', '/api/v1/tokens'],
    ['POST', '/api/v1/tokens/revoke-all'],
    ['GET', '/api/v1/tokens/:id'],
    ['DELETE', '/api/v1/tokens/:id'],
    ['POST', '/api/v1/tokens/:id/renew'],

    ['GET', '/api/v1/certificates/expiring'],
    ['GET', '/api/v1/notifications'],
    ['POST', '/api/v1/notifications/read-all'],
    ['POST', '/api/v1/notifications/:id/read'],
    ['POST', '/api/v1/admin/certificates/notify-now'],

    ['GET', '/api/v1/webhooks/events'],
    ['GET', '/api/v1/webhooks'],
    ['POST', '/api/v1/webhooks'],
    ['GET', '/api/v1/webhooks/:id'],
    ['PUT', '/api/v1/webhooks/:id'],
    ['DELETE', '/api/v1/webhooks/:id'],
    ['POST', '/api/v1/webhooks/:id/rotate-secret'],
    ['GET', '/api/v1/webhooks/:id/deliveries'],
  ];

  it('debe exponer todos los endpoints públicos de Laravel como públicos', () => {
    for (const [method, path] of expectedPublic) {
      const key = asKey(method, path);
      const route = routeMap.get(key);
      expect(route).toBeDefined();
      expect(route?.guarded).toBe(false);
    }
  });

  it('debe exponer todos los endpoints protegidos de Laravel como protegidos', () => {
    const mismatches: string[] = [];

    for (const [method, path] of expectedProtected) {
      const key = asKey(method, path);
      const route = routeMap.get(key);
      if (!route) {
        mismatches.push(`${key} -> missing`);
        continue;
      }
      if (!route.guarded) {
        mismatches.push(`${key} -> unguarded`);
      }
    }

    expect(mismatches).toEqual([]);
  });
});

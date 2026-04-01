import {
  CallHandler,
  ExecutionContext,
  Injectable,
  NestInterceptor,
} from '@nestjs/common';
import { FastifyRequest } from 'fastify';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

export interface LaravelPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  path: string;
  first_page_url: string;
  last_page_url: string;
  next_page_url: string | null;
  prev_page_url: string | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface PaginatedServiceResult<T = any> {
  items: T[];
  meta: {
    currentPage: number;
    totalPages: number;
    itemsPerPage: number;
    totalItems: number;
  };
}

/**
 * Intercepta respuestas paginadas (que contengan la clave __paginated: true)
 * y las transforma al formato Laravel exacto.
 *
 * Los servicios deben devolver:
 * { __paginated: true, items: T[], meta: { currentPage, totalPages, itemsPerPage, totalItems } }
 */
@Injectable()
export class LaravelPaginationInterceptor implements NestInterceptor {
  intercept(context: ExecutionContext, next: CallHandler): Observable<any> {
    const request = context
      .switchToHttp()
      .getRequest<FastifyRequest & { url: string }>();

    return next.handle().pipe(
      map((value) => {
        if (!value || !value.__paginated) {
          return value;
        }

        const { items, meta } = value as PaginatedServiceResult & {
          __paginated: true;
        };
        const { currentPage, totalPages, itemsPerPage, totalItems } = meta;

        const baseUrl = this.buildBaseUrl(request);
        const from = totalItems === 0 ? null : (currentPage - 1) * itemsPerPage + 1;
        const to = totalItems === 0 ? null : Math.min(currentPage * itemsPerPage, totalItems);

        const links = this.buildLinks(baseUrl, currentPage, totalPages);

        const dataRecords = {
          data: items,
          current_page: currentPage,
          last_page: totalPages,
          per_page: itemsPerPage,
          total: totalItems,
          from,
          to,
          path: baseUrl,
          first_page_url: `${baseUrl}?page=1`,
          last_page_url: `${baseUrl}?page=${totalPages}`,
          next_page_url:
            currentPage < totalPages
              ? `${baseUrl}?page=${currentPage + 1}`
              : null,
          prev_page_url:
            currentPage > 1 ? `${baseUrl}?page=${currentPage - 1}` : null,
          links,
        };

        return { success: true, dataRecords };
      }),
    );
  }

  private buildBaseUrl(request: FastifyRequest & { url: string }): string {
    const proto =
      (request.headers['x-forwarded-proto'] as string) ?? 'http';
    const host = request.headers['host'] ?? 'localhost';
    const urlWithoutQuery = request.url.split('?')[0];
    return `${proto}://${host}${urlWithoutQuery}`;
  }

  private buildLinks(
    baseUrl: string,
    currentPage: number,
    lastPage: number,
  ): Array<{ url: string | null; label: string; active: boolean }> {
    const links: Array<{ url: string | null; label: string; active: boolean }> =
      [];

    // Previous
    links.push({
      url: currentPage > 1 ? `${baseUrl}?page=${currentPage - 1}` : null,
      label: '&laquo; Previous',
      active: false,
    });

    // Numbered pages
    for (let p = 1; p <= lastPage; p++) {
      links.push({
        url: `${baseUrl}?page=${p}`,
        label: String(p),
        active: p === currentPage,
      });
    }

    // Next
    links.push({
      url:
        currentPage < lastPage ? `${baseUrl}?page=${currentPage + 1}` : null,
      label: 'Next &raquo;',
      active: false,
    });

    return links;
  }
}

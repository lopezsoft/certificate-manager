import type { SidebarsConfig } from "@docusaurus/plugin-content-docs";

const sidebar: SidebarsConfig = {
  apisidebar: [
    {
      type: "doc",
      id: "api/certificate-manager-api",
    },
    {
      type: "category",
      label: "Autenticación",
      link: {
        type: "doc",
        id: "api/autenticacion",
      },
      items: [
        {
          type: "doc",
          id: "api/8-cb-7742-ae-98-d-0990-acb-2907-c-4-e-00-bf-24",
          label: "Iniciar sesión",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/b-0190-b-67-ea-8-c-4-b-1-e-0-ee-19077-f-390-c-4-ab",
          label: "Cerrar sesión",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/7-c-01-a-354-a-5960445207-bc-5-d-2-f-17-d-547-e",
          label: "Reenviar correo de verificación de email",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/24-b-1-c-68468-bf-984-b-8806908-f-211-f-3827",
          label: "Restablecer contraseña",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/888-e-4-aa-5-b-202-b-170-b-9-fe-831-da-90048-c-6",
          label: "Solicitar restablecimiento de contraseña",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/573-de-1-fed-352-c-1205-a-32-c-4-d-1-b-9877375",
          label: "Registrar nueva empresa y usuario",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/bb-4-b-893-f-5-bd-606-e-136-b-93-d-41-b-55-b-943-f",
          label: "Verificar dirección de correo electrónico",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "Datos Maestros",
      link: {
        type: "doc",
        id: "api/datos-maestros",
      },
      items: [
        {
          type: "doc",
          id: "api/45-d-1-df-8-f-6092542949-f-73-b-6779-b-2-fec-2",
          label: "Listar países activos",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/013162-ba-202815-ff-66-de-50-a-16-dbfd-33-c",
          label: "Listar departamentos",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/3-e-06-ade-7-a-07821-d-5-c-7-bbd-6-d-3-a-207-f-24-e",
          label: "Listar ciudades",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/b-2-d-86-cd-20-d-022-c-242-e-11-a-6-f-8203574-c-0",
          label: "Listar tipos de organización",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/d-2-e-7-ea-265-c-7-edf-33-fa-13-a-8-b-55-a-0-b-989-d",
          label: "Listar tipos de documento de identidad",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/9-e-5895-db-6-fe-8-d-67-bc-619300596-ad-6-ef-9",
          label: "Listar tipos de documento constitutivo (Cámara de Comercio, etc.)",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "Solicitudes de Certificado",
      link: {
        type: "doc",
        id: "api/solicitudes-de-certificado",
      },
      items: [
        {
          type: "doc",
          id: "api/c-42-bb-2-f-191-e-46810104726026-a-014-fd-3",
          label: "Listar mis solicitudes",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/8-cbf-351-b-63-af-3-d-2435-b-0538-bb-7-f-08-d-59",
          label: "Crear solicitud de certificado",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/228-b-2949890-edab-1-ec-3739-bb-48158350",
          label: "Obtener detalle de una solicitud",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/53-a-34-b-911-dac-0-c-039-f-52-f-0-ab-50-f-626-d-1",
          label: "Actualizar solicitud de certificado",
          className: "api-method put",
        },
        {
          type: "doc",
          id: "api/afd-6293-e-280-fa-0-aaa-201-cab-99575-d-534",
          label: "Eliminar solicitud de certificado",
          className: "api-method delete",
        },
        {
          type: "doc",
          id: "api/78-e-0656646734-abca-73-c-742-c-93-f-9-ad-93",
          label: "Obtener el historial de cambios de una solicitud de certificado",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/cb-94-ebbccac-485-c-4-db-438-c-3-e-3-b-934-a-7-e",
          label: "Actualizar estado de una solicitud",
          className: "api-method put",
        },
        {
          type: "doc",
          id: "api/c-092-c-3-c-0-bce-9-e-2-ed-7335-ab-9-e-19827841",
          label: "Consultar última solicitud por NIT",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/37-be-4-ff-2-a-4-ddf-6-d-3-cbc-2-e-5-c-96-cad-26-c-8",
          label: "Estadísticas de solicitudes por año",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "Archivos",
      link: {
        type: "doc",
        id: "api/archivos",
      },
      items: [
        {
          type: "doc",
          id: "api/9-d-78-f-7-cf-6056-dc-9-c-37-cb-23-eeb-7326183",
          label: "Subir archivo a una solicitud",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/32-e-3-e-6-ee-01-a-113-e-14046-f-93-f-54886-fb-9",
          label: "Eliminar un archivo de una solicitud",
          className: "api-method delete",
        },
      ],
    },
    {
      type: "category",
      label: "Emisión de Certificados",
      link: {
        type: "doc",
        id: "api/emision-de-certificados",
      },
      items: [
        {
          type: "doc",
          id: "api/certificate-request-issue",
          label: "Disparar emisión del certificado (provider-agnostic)",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/certificate-request-issuance-show",
          label: "Consultar estado del trámite",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/certificate-request-issuance-download",
          label: "Metadata de descarga del P12 (sólo Viafirma)",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/certificate-request-issuance-download-base-64",
          label: "Descarga del P12 en Base64",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/certificate-request-issuance-renew",
          label: "Genera orden de renovación de un certificado",
          className: "api-method post",
        },
      ],
    },
    {
      type: "category",
      label: "Viafirma",
      link: {
        type: "doc",
        id: "api/viafirma",
      },
      items: [
        {
          type: "doc",
          id: "api/a-15-f-636-cb-8-c-9416294-cbfb-7-b-04-af-9-a-64",
          label: "Obtener link del portal KYC de acreditación",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/c-52-d-2-d-4-bd-5-e-7-ef-8-cb-0-d-8-bd-3981-f-01-c-2-e",
          label: "Revocar un certificado Viafirma ya emitido",
          className: "api-method post",
        },
      ],
    },
    {
      type: "category",
      label: "Perfil",
      link: {
        type: "doc",
        id: "api/perfil",
      },
      items: [
        {
          type: "doc",
          id: "api/86-d-0-d-014-fbad-3663-eca-93-f-68165-e-089-e",
          label: "Listar tipos de usuario",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/98-ad-4-aedd-40-e-987-b-1-f-14429-cc-045-edfc",
          label: "Obtener perfil del usuario autenticado",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/803-c-746-d-569-f-96949-d-024-b-52-f-2764373",
          label: "Actualizar perfil de usuario",
          className: "api-method put",
        },
      ],
    },
    {
      type: "category",
      label: "Consumo",
      link: {
        type: "doc",
        id: "api/consumo",
      },
      items: [
        {
          type: "doc",
          id: "api/81-d-3-b-16-e-1-da-6414-d-758-af-81-faaecbd-75",
          label: "Consumo de documentos por año",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/e-791-eaf-6526-c-1-d-248-f-43-c-735-ada-1-edad",
          label: "Consumo de documentos por mes",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "CRUD Genérico",
      link: {
        type: "doc",
        id: "api/crud-generico",
      },
      items: [
        {
          type: "doc",
          id: "api/d-4-f-8667-d-43-b-756645-f-46-f-96256-ab-9249",
          label: "Listar registros de una tabla",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/539-edbefdc-3-ab-3-cd-9-ce-11-c-75-c-1-d-01640",
          label: "Crear registro en una tabla",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/11-e-79-f-46-c-48-a-0-fbe-65-c-697818-c-3-f-007-b",
          label: "Obtener un registro por ID",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/f-2529-a-26-f-1-e-068-cfd-611-caf-34953-ffaf",
          label: "Actualizar un registro",
          className: "api-method put",
        },
        {
          type: "doc",
          id: "api/226-e-10994-ccc-5-c-3-ef-16-dd-2-c-1-f-6-e-9-f-889",
          label: "Eliminar un registro",
          className: "api-method delete",
        },
      ],
    },
    {
      type: "category",
      label: "Tokens",
      link: {
        type: "doc",
        id: "api/tokens",
      },
      items: [
        {
          type: "doc",
          id: "api/62-a-533924-bf-4-cedeb-69-ca-2274-ef-20-d-02",
          label: "Listar tokens activos del usuario",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/d-5-f-431358-f-9-ce-7-e-8-bc-4-bf-81-b-18877-fe-0",
          label: "Crear Personal Access Token",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/7-ed-6-e-4450-f-398-a-190-fcd-3-b-8-e-473-e-7257",
          label: "Detalle de un token",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/c-09-d-4-a-9-dc-2326-f-0-d-5-c-540-f-20-fc-5-c-36-bd",
          label: "Revocar un token",
          className: "api-method delete",
        },
        {
          type: "doc",
          id: "api/33-f-4-acb-9260-c-75036718-c-2-b-143-ce-63-f-6",
          label: "Revocar todos los tokens",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/71456938-b-349396-a-0-d-0-b-0-fc-3566-ff-761",
          label: "Renovar un token",
          className: "api-method post",
        },
      ],
    },
    {
      type: "category",
      label: "Webhooks",
      link: {
        type: "doc",
        id: "api/webhooks",
      },
      items: [
        {
          type: "doc",
          id: "api/067461-ae-7-dcfcd-0-c-9-c-6057-f-0-bcea-014-d",
          label: "Historial de entregas de un webhook",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/74-ed-8-a-16-eacdbe-9-f-4-c-00-a-76602881090",
          label: "Listar tipos de evento disponibles",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/9-c-13-b-981583-be-96-b-1-e-7-bcc-059-ceef-6-f-2",
          label: "Listar webhooks de la compañía",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/963-e-4-c-34-b-6-e-774-ed-3-dffb-0339-ae-491-c-7",
          label: "Crear webhook",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/27-c-1-cbf-2-e-30-b-1-c-933380347-a-2-eb-4-ebb-8",
          label: "Obtener detalle de un webhook",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/a-2309314-e-5-fb-571-e-00563-f-03-f-6-e-92227",
          label: "Actualizar webhook",
          className: "api-method put",
        },
        {
          type: "doc",
          id: "api/e-286120-dfa-8-a-7-cdb-29-aea-0092-f-21-fc-89",
          label: "Eliminar webhook",
          className: "api-method delete",
        },
        {
          type: "doc",
          id: "api/7840-e-524-f-031-f-846-c-3-d-3-a-74-e-43-bc-9-ed-1",
          label: "Rotar secret del webhook",
          className: "api-method post",
        },
      ],
    },
    {
      type: "category",
      label: "Notificaciones",
      link: {
        type: "doc",
        id: "api/notificaciones",
      },
      items: [
        {
          type: "doc",
          id: "api/b-32-bbb-4-c-709-f-16-d-8-b-2-ac-3-c-41-ac-16701-a",
          label: "Certificados próximos a vencer",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/0-f-031-f-66-cd-8-c-52-bf-73-e-89-fb-2-d-050-ad-83",
          label: "Listar notificaciones del usuario autenticado",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/e-8-ec-77-e-64-be-528452-b-6-b-9-ee-62-d-832-d-70",
          label: "Marcar notificación como leída",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/e-873443612632-a-319-ae-0-f-88-db-368-dd-7-a",
          label: "Marcar todas las notificaciones como leídas",
          className: "api-method post",
        },
      ],
    },
    {
      type: "category",
      label: "Configuración",
      link: {
        type: "doc",
        id: "api/configuracion",
      },
      items: [
        {
          type: "doc",
          id: "api/16-e-387654-c-453-d-9-bb-4-a-444-c-16-dc-6-e-2-c-6",
          label: "Obtener configuración general de la empresa",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/2-fe-7642020-a-8-a-5-b-42-df-33-d-05-ce-8355-c-2",
          label: "Actualizar configuración general de la empresa",
          className: "api-method put",
        },
        {
          type: "doc",
          id: "api/4-fe-8-a-45-ab-813253-de-6-aed-421549-d-5-b-34",
          label: "Obtener datos de la empresa autenticada",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/bd-87-b-570-c-00-c-250-f-303-d-4-a-1041-f-53-daf",
          label: "Obtener configuración de encabezados de reportes",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/5-d-30-e-0-f-4-e-8-b-8-e-4-b-9-a-55-e-40-faaa-7-dacbb",
          label: "Actualizar encabezado de reporte",
          className: "api-method put",
        },
      ],
    },
    {
      type: "category",
      label: "Órdenes",
      link: {
        type: "doc",
        id: "api/ordenes",
      },
      items: [
        {
          type: "doc",
          id: "api/325-fd-3-d-55-bbe-9-bb-39645269928-fa-4983",
          label: "Listar órdenes de compra de la empresa",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/445-bab-4-f-7-dc-9-ea-6-fcd-54-f-286-c-0209478",
          label: "Crear orden de compra de certificados",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/8-e-48-dcef-1411-a-9-d-3-c-0-fe-571-cd-944514-e",
          label: "Ver detalle de una orden",
          className: "api-method get",
        },
        {
          type: "doc",
          id: "api/531-bc-96651587-c-55627843-f-92-ce-88-f-3-c",
          label: "Eliminar una orden PENDING",
          className: "api-method delete",
        },
        {
          type: "doc",
          id: "api/3-a-9-b-1-bbd-2553601-ada-362-f-5-c-7350-c-681",
          label: "Ejecutar pago de una orden",
          className: "api-method post",
        },
        {
          type: "doc",
          id: "api/e-9918-c-2-eb-1-dd-27-ea-25-cabb-20-f-538-b-154",
          label: "Reintentar pago de una orden PENDING",
          className: "api-method post",
        },
      ],
    },
    {
      type: "category",
      label: "Precios",
      link: {
        type: "doc",
        id: "api/precios",
      },
      items: [
        {
          type: "doc",
          id: "api/4-d-6527-b-3-a-7-d-4114-c-7-f-096-f-1-caa-2-f-9605",
          label: "Consultar tarifas de certificados",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "Pagos Externos",
      link: {
        type: "doc",
        id: "api/pagos-externos",
      },
      items: [
        {
          type: "doc",
          id: "api/c-8-d-42-e-7-b-0-b-7081379-abe-026-bee-89-c-703",
          label: "Recibir evento de pago de WOMPI",
          className: "api-method post",
        },
      ],
    },
    {
      type: "category",
      label: "Sistema",
      link: {
        type: "doc",
        id: "api/sistema",
      },
      items: [
        {
          type: "doc",
          id: "api/49-a-59-c-9-e-68466-fb-63-f-7-f-95-e-7-a-62337-d-5",
          label: "Estado de salud de los servicios externos",
          className: "api-method get",
        },
      ],
    },
    {
      type: "category",
      label: "Sincronización",
      link: {
        type: "doc",
        id: "api/sincronizacion",
      },
      items: [
        {
          type: "doc",
          id: "api/77-a-8-ae-6-ddd-9-b-45922508-ac-3-ba-9-abb-377",
          label: "Sincronizar cuenta desde sistema externo (ERP/API)",
          className: "api-method post",
        },
      ],
    },
    {
      type: "category",
      label: "Cupos",
      link: {
        type: "doc",
        id: "api/cupos",
      },
      items: [
        {
          type: "doc",
          id: "api/quota-status",
          label: "Consultar disponibilidad de certificados",
          className: "api-method get",
        },
      ],
    },
  ],
};

export default sidebar.apisidebar;

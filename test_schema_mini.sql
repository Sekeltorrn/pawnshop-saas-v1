-- MINIMAL TEST SCHEMA FOR CLONE ENGINE PIPELINE

create table roles (
  role_id uuid not null default gen_random_uuid (),
  role_name character varying(50) not null,
  permissions jsonb null,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint roles_pkey primary key (role_id),
  constraint roles_name_key unique (role_name)
);

create table tenant_settings (
  setting_id uuid not null default gen_random_uuid (),
  shop_name character varying(100) null,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint tenant_settings_pkey primary key (setting_id)
);
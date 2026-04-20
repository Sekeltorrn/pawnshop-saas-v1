create table audit_logs (
  log_id uuid not null default gen_random_uuid (),
  employee_id uuid null,
  action_type character varying(50) not null,
  table_affected character varying(50) not null,
  record_id character varying(255) null,
  old_data jsonb null,
  new_data jsonb null,
  ip_address character varying(45) null,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint audit_logs_pkey primary key (log_id),
  constraint audit_logs_employee_id_fkey foreign KEY (employee_id) references employees (employee_id) on delete set null
);


create table categories (
  category_id uuid not null default gen_random_uuid (),
  category_name character varying(50) not null,
  description text null,
  default_interest_rate numeric(5, 2) null default 3.00,
  is_active boolean not null default true,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint categories_pkey primary key (category_id),
  constraint categories_category_name_key unique (category_name)
);

create trigger update_categories_modtime BEFORE
update on categories for EACH row
execute FUNCTION update_updated_at_column ();


create table asset_matrix (
    node_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    category_id UUID NOT NULL, -- Links to your 'categories' table
    parent_id UUID NULL,       -- Self-reference for hierarchy
    name VARCHAR(100) NOT NULL,
    hierarchy_level VARCHAR(50) NOT NULL, -- 'L2_Classification', 'L3_Brand', etc.
    is_accepted BOOLEAN DEFAULT TRUE,
    base_appraisal_value NUMERIC(15, 2) NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()),
    
    CONSTRAINT fk_asset_parent FOREIGN KEY (parent_id) REFERENCES asset_matrix (node_id) ON DELETE CASCADE,
    CONSTRAINT fk_matrix_category FOREIGN KEY (category_id) REFERENCES categories (category_id) ON DELETE CASCADE,
    CONSTRAINT unique_name_per_parent UNIQUE (category_id, parent_id, name, hierarchy_level)
);


create table asset_attributes (
    attribute_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    node_id UUID NOT NULL, -- Links to L2 (Classification)
    label VARCHAR(100) NOT NULL,
    field_type VARCHAR(20) NOT NULL, -- 'select', 'text', 'number'
    options JSONB NULL,              -- e.g., ["128GB", "256GB"]
    is_required BOOLEAN DEFAULT TRUE,
    
    CONSTRAINT fk_attr_node FOREIGN KEY (node_id) REFERENCES asset_matrix (node_id) ON DELETE CASCADE
);


create table asset_tests (
    test_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    node_id UUID NOT NULL, -- Specifically links to the L2_Classification (e.g., 'Smartphone')
    test_name VARCHAR(100) NOT NULL, -- e.g., 'Screen Crack Check'
    test_group VARCHAR(50) NOT NULL, -- e.g., 'Display & Touch'
    impact_type VARCHAR(20) DEFAULT 'penalty', -- 'penalty' (minus $) or 'bonus' (plus $)
    impact_value NUMERIC(5, 2) NULL, -- e.g., 10.00 (represents a 10% deduction)
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()),
    
    -- Ensures the tests are wiped if the classification is deleted
    CONSTRAINT fk_test_node FOREIGN KEY (node_id) 
        REFERENCES asset_matrix (node_id) ON DELETE CASCADE
);


create table customers (
  customer_id uuid not null default gen_random_uuid (),
  auth_user_id uuid null,
  first_name character varying(50) not null,
  last_name character varying(50) not null,
  email character varying(100) null,
  contact_no character varying(20) null,
  address text null,
  id_type character varying(50) null,
  id_number character varying(50) null,
  status character varying(20) null default 'pending'::character varying,
  is_walk_in boolean null default false,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  password text null,
  middle_name character varying(50) null,
  birthday date null,
  id_photo_front_url text null,
  id_photo_back_url text null,
  rejection_reason text null,
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  deleted_at timestamp with time zone null,
  constraint customers_pkey primary key (customer_id),
  constraint customers_auth_user_id_key unique (auth_user_id),
  constraint customers_email_key unique (email)
);

create trigger update_tenant_customers_modtime BEFORE
update on customers for EACH row
execute FUNCTION update_updated_at_column ();


create table employees (
  employee_id uuid not null default gen_random_uuid (),
  auth_user_id uuid null,
  role_id uuid not null,
  first_name character varying(50) not null,
  last_name character varying(50) not null,
  email character varying(100) not null,
  phone_number character varying(20) null,
  password_hash text null,
  status character varying(20) not null default 'active'::character varying,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  deleted_at timestamp with time zone null,
  constraint employees_pkey primary key (employee_id),
  constraint employees_auth_key unique (auth_user_id),
  constraint employees_email_key unique (email),
  constraint employees_role_id_fkey foreign KEY (role_id) references roles (role_id) on delete RESTRICT
);

create trigger update_tenant_employees_modtime BEFORE
update on employees for EACH row
execute FUNCTION update_updated_at_column ();


create table inventory (
  item_id uuid not null default gen_random_uuid (),
  category_id uuid not null,
  item_name character varying(255) not null,
  item_description text null,
  item_condition text null,
  serial_number character varying(100) null,
  weight_grams numeric(10, 2) null,
  appraised_value numeric(15, 2) null,
  item_status character varying(20) null default 'in_vault'::character varying,
  item_image text null,
  storage_location text null,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  retail_price numeric(15, 2) null,
  lot_name character varying(100) null,
  lot_price numeric(15, 2) null,
  constraint inventory_pkey primary key (item_id),
  constraint inventory_category_id_fkey foreign KEY (category_id) references categories (category_id) on delete RESTRICT
);

create trigger update_inventory_modtime BEFORE
update on inventory for EACH row
execute FUNCTION update_updated_at_column ();


create table storage_locations (
  location_id uuid not null default gen_random_uuid (),
  location_name character varying(255) not null,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint storage_locations_pkey primary key (location_id)
) TABLESPACE pg_default;


create table retail_lots (
  lot_name character varying(100) not null,
  lot_price numeric(15, 2) null,
  created_at timestamp without time zone null default CURRENT_TIMESTAMP,
  constraint retail_lots_pkey primary key (lot_name)
)


create table loans (
  loan_id uuid not null default gen_random_uuid (),
  customer_id uuid null,
  item_id uuid null,
  pawn_ticket_no serial not null,
  principal_amount numeric(15, 2) not null,
  interest_rate numeric(5, 2) not null,
  loan_date date null default CURRENT_DATE,
  due_date date not null,
  status character varying(20) null default 'active'::character varying,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  service_charge numeric(10, 2) null default 5.00,
  net_proceeds numeric(10, 2) null,
  employee_id uuid null,
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  reference_no character varying(50) null,
  expiry_date date null,
  reminder_sent boolean null default false,
  shift_id uuid null,
  admin_notes text null,
  constraint loans_pkey primary key (loan_id),
  constraint loans_reference_no_key unique (reference_no),
  constraint loans_customer_id_fkey foreign KEY (customer_id) references customers (customer_id),
  constraint loans_employee_id_fkey foreign KEY (employee_id) references employees (employee_id) on delete set null,
  constraint loans_item_id_fkey foreign KEY (item_id) references inventory (item_id) on delete set null
);

create trigger update_tenant_loans_modtime BEFORE
update on loans for EACH row
execute FUNCTION update_updated_at_column ();


create table notifications (
  notification_id uuid not null default gen_random_uuid (),
  customer_id uuid not null,
  type character varying(50) not null,
  title character varying(100) not null,
  message text not null,
  is_read boolean not null default false,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint notifications_pkey primary key (notification_id),
  constraint notifications_customer_id_fkey foreign KEY (customer_id) references customers (customer_id) on delete CASCADE
);


create table payments (
  payment_id uuid not null default gen_random_uuid (),
  loan_id uuid null,
  amount numeric(15, 2) not null,
  payment_date timestamp with time zone null default timezone ('utc'::text, now()),
  payment_type character varying(20) null,
  or_number character varying(50) null,
  reference_number text null,
  payment_channel character varying(50) null default 'Walk-In'::character varying,
  employee_id uuid null,
  shift_id uuid null,
  status character varying(20) null default 'completed'::character varying,
  notes text null,
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  interest_paid numeric(15, 2) null default 0,
  penalty_paid numeric(15, 2) null default 0,
  service_fee_paid numeric(15, 2) null default 0,
  principal_paid numeric(15, 2) null default 0,
  constraint payments_pkey primary key (payment_id),
  constraint payments_employee_id_fkey foreign KEY (employee_id) references employees (employee_id) on delete set null,
  constraint payments_loan_id_fkey foreign KEY (loan_id) references loans (loan_id),
  constraint payments_shift_id_fkey foreign KEY (shift_id) references shifts (shift_id) on delete set null
);

create trigger update_tenant_payments_modtime BEFORE
update on payments for EACH row
execute FUNCTION update_updated_at_column ();


create table roles (
  role_id uuid not null default gen_random_uuid (),
  role_name character varying(50) not null,
  permissions jsonb null,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint roles_pkey primary key (role_id),
  constraint roles_name_key unique (role_name)
);

create trigger update_tenant_roles_modtime BEFORE
update on roles for EACH row
execute FUNCTION update_updated_at_column ();


create table shifts (
  shift_id uuid not null default gen_random_uuid (),
  employee_id uuid not null,
  starting_cash numeric(15, 2) not null,
  actual_closing_cash numeric(15, 2) null,
  expected_cash numeric(15, 2) null default 0.00,
  variance numeric(15, 2) null,
  start_time timestamp with time zone not null default timezone ('utc'::text, now()),
  end_time timestamp with time zone null,
  status character varying(20) null default 'Open'::character varying,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint shifts_pkey primary key (shift_id),
  constraint shifts_employee_id_fkey foreign KEY (employee_id) references employees (employee_id) on delete RESTRICT
);

create trigger update_tenant_shifts_modtime BEFORE
update on shifts for EACH row
execute FUNCTION update_updated_at_column ();


create table tenant_settings (
  setting_id uuid not null default gen_random_uuid (),
  ltv_percentage numeric(5, 2) null default 60.00,
  interest_rate numeric(5, 2) null default 3.50,
  service_fee numeric(10, 2) null default 5.00,
  penalty_rate numeric(10, 2) null default 2.00,
  grace_period_days integer null default 3,
  gold_rate_18k numeric(10, 2) null default 3000.00,
  gold_rate_21k numeric(10, 2) null default 3500.00,
  gold_rate_24k numeric(10, 2) null default 4200.00,
  diamond_base_rate numeric(15, 2) null default 50000.00,
  shop_name character varying(100) null,
  receipt_header_text text null,
  timezone character varying(50) not null default 'Asia/Manila'::character varying,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  store_open_time time without time zone null default '08:00:00'::time without time zone,
  store_close_time time without time zone null default '17:00:00'::time without time zone,
  closed_days jsonb null default '["Sunday"]'::jsonb,
  portal_title character varying(100) null,
  portal_tagline text null,
  portal_bg_color character varying(7) null default '#0a0b0d'::character varying,
  portal_btn_color character varying(7) null default '#ff6b00'::character varying,
  portal_logo_url text null,
  portal_custom_blocks jsonb null default '[{"icon": "location_on", "title": "Location", "content": "1245 Opulence Avenue\nSuite 200\nMetropolis, NY 10022"}, {"icon": "call", "title": "Contact", "content": "+1 (555) 867-5309\nconcierge@merlinpawnshop.com"}, {"icon": "schedule", "title": "Hours", "content": "Mon - Fri: 10:00 AM - 6:00 PM\nSat: 11:00 AM - 4:00 PM\nSun: By Appointment Only"}]'::jsonb,
  admin_bg_color character varying(50) null default '#05010a'::character varying,
  admin_btn_color character varying(50) null default '#ff6a00'::character varying,
  admin_text_color character varying(50) null default '#ffffff'::character varying,
  constraint tenant_settings_pkey primary key (setting_id)
);

create trigger update_tenant_settings_modtime BEFORE
update on tenant_settings for EACH row
execute FUNCTION update_updated_at_column ();


create table profile_change_requests (
  request_id uuid not null default gen_random_uuid (),
  customer_id uuid not null,
  requested_email character varying(100) null,
  requested_contact_no character varying(20) null,
  requested_address text null,
  status character varying(20) not null default 'pending'::character varying,
  admin_notes text null,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint profile_change_requests_pkey primary key (request_id),
  constraint profile_change_requests_customer_id_fkey foreign KEY (customer_id) references customers (customer_id) on delete CASCADE
);

create trigger update_profile_change_modtime BEFORE
update on profile_change_requests for EACH row
execute FUNCTION update_updated_at_column ();


create table retail_lots (
  lot_name character varying(100) not null,
  lot_price numeric(15, 2) null,
  created_at timestamp without time zone null default CURRENT_TIMESTAMP,
  constraint retail_lots_pkey primary key (lot_name)
);


create table appointments (
  appointment_id uuid not null default gen_random_uuid (),
  customer_id uuid not null,
  appointment_date date not null,
  appointment_time time without time zone not null,
  purpose character varying(50) not null,
  remarks text null,
  status character varying(20) not null default 'pending'::character varying,
  created_at timestamp with time zone null default timezone ('utc'::text, now()),
  updated_at timestamp with time zone null default timezone ('utc'::text, now()),
  constraint appointments_pkey primary key (appointment_id),
  constraint appointments_customer_id_fkey foreign KEY (customer_id) references tenant_pwn_18e601.customers (customer_id) on delete CASCADE
);

create trigger update_tenant_appointments_modtime BEFORE
update on appointments for EACH row
execute FUNCTION update_updated_at_column ();

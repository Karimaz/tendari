-- Additional regional portals researched for the Tendari hub (all disabled by default).
INSERT OR IGNORE INTO sources (code, name, region, connector_class, fetch_interval_min, enabled) VALUES
  ('sa_aramco',       'Saudi Aramco (e-Marketplace)',  'mena',   'Tendari\Connectors\Web\RegistrationConnector', 1440, 0),
  ('ae_adgpg',        'Abu Dhabi Procurement Gate',    'mena',   'Tendari\Connectors\Web\RegistrationConnector', 720,  0),
  ('ae_dubai_esupply','Dubai eSupply',                 'mena',   'Tendari\Connectors\Web\RegistrationConnector', 720,  0),
  ('ae_federal',      'UAE Federal Supplier Register', 'mena',   'Tendari\Connectors\Web\RegistrationConnector', 1440, 0),
  ('qa_monaqasat',    'Qatar Monaqasat',               'mena',   'Tendari\Connectors\Web\PendingConnector',      720,  0),
  ('et_egp',          'Ethiopia e-GP',                 'africa', 'Tendari\Connectors\Web\PendingConnector',      720,  0),
  ('eg_etenders',     'Egypt eTenders',                'mena',   'Tendari\Connectors\Web\PendingConnector',      720,  0);

UPDATE sources SET name = 'Tanzania NeST (ex-TANePS)' WHERE code = 'tz_taneps';
UPDATE sources SET name = 'Ghana GHANEPS / PPA'       WHERE code = 'gh_ghaneps';

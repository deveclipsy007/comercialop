-- ============================================================
-- Operon Intelligence Platform — Seed Data (Development)
-- ============================================================

-- Default tenant (demo agency)
INSERT OR IGNORE INTO tenants (id, name, slug, plan, settings) VALUES (
    'tenant_demo_001',
    'Agência Nexus Digital',
    'nexus-digital',
    'pro',
    '{"primaryColor":"#18C29C","city":"São Paulo","niche":"Marketing Digital"}'
);

-- Default admin user (password: operon123)
INSERT OR IGNORE INTO users (id, tenant_id, name, email, password, role) VALUES (
    'user_admin_001',
    'tenant_demo_001',
    'Admin Operon',
    'admin@operon.ai',
    '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TgxwchLwWwvhqXkiJJWPrfJnS9TS',
    'admin'
);

-- Agency settings
INSERT OR IGNORE INTO agency_settings (id, tenant_id, agency_name, agency_city, agency_niche, offer_summary, differentials, services, icp_profile) VALUES (
    'settings_001',
    'tenant_demo_001',
    'Agência Nexus Digital',
    'São Paulo',
    'Marketing Digital B2B',
    'Transformamos negócios locais em marcas digitais dominantes com IA e estratégia agressiva.',
    '["IA integrada em todos os processos","Cases comprovados com +300% de crescimento","Gestor dedicado com acesso direto","Relatórios semanais com dados reais"]',
    '[{"name":"Landing Page Premium","price":2500},{"name":"Gestão de Tráfego","price":1800},{"name":"SEO Local","price":1200},{"name":"Automação WhatsApp","price":900},{"name":"Pacote Completo","price":4500}]',
    'Empresas locais com faturamento entre R$50k-500k/mês, com presença digital fraca ou inexistente, que perdem clientes para concorrência online.'
);

-- Token quota for demo tenant
INSERT OR IGNORE INTO token_quotas (id, tenant_id, tokens_used, tokens_limit, tier, reset_at) VALUES (
    'quota_demo_001',
    'tenant_demo_001',
    47,
    500,
    'pro',
    datetime('now', '+1 day')
);

-- Sample leads
INSERT OR IGNORE INTO leads (id, tenant_id, name, segment, website, phone, address, pipeline_status, priority_score, fit_score, analysis, social_presence) VALUES (
    'lead_001',
    'tenant_demo_001',
    'Restaurante Dom Pepe',
    'Alimentação',
    'https://dompepe.com.br',
    '(11) 99234-5678',
    'Rua Augusta, 1200, Consolação, São Paulo - SP',
    'contacted',
    82,
    78,
    '{"priorityScore":82,"digitalMaturity":"Média","diagnosis":["Site desatualizado sem versão mobile","Instagram com baixo engajamento","Sem presença no Google Maps"],"opportunities":["Cardápio digital com pedidos online","Gestão de reputação Google","Campanhas de tráfego pago local"],"urgencyLevel":"Alta","fitScore":78,"summary":"Restaurante com boa reputação local mas presença digital defasada. Alto potencial para serviços de presença digital."}',
    '{"instagram":"@restaurantedompepe","facebook":"restaurantedompepe","linkedin":""}'
),
(
    'lead_002',
    'tenant_demo_001',
    'Clínica Sorrir Mais',
    'Saúde / Odontologia',
    '',
    '(11) 3456-7890',
    'Av. Paulista, 900, Bela Vista, São Paulo - SP',
    'qualified',
    91,
    88,
    '{"priorityScore":91,"digitalMaturity":"Baixa","diagnosis":["Sem site próprio","Zero presença em redes sociais","Não aparece no Google Meu Negócio"],"opportunities":["Site profissional com agendamento online","Captação de pacientes via tráfego pago","Gestão completa de redes sociais"],"urgencyLevel":"Alta","fitScore":88,"summary":"Clínica odontológica sem NENHUMA presença digital. Oportunidade máxima para todos os serviços."}',
    '{"instagram":"","facebook":"","linkedin":""}'
),
(
    'lead_003',
    'tenant_demo_001',
    'Academia FitForce',
    'Fitness & Bem-estar',
    'https://fitforce.com.br',
    '(11) 98765-4321',
    'Rua Oscar Freire, 500, Jardins, São Paulo - SP',
    'proposal',
    67,
    71,
    '{"priorityScore":67,"digitalMaturity":"Média","diagnosis":["Site lento (Performance: 42/100)","Instagram sem estratégia de conteúdo","Baixa conversão de leads online"],"opportunities":["Otimização técnica do site","Funil de captação de alunos","Automação de follow-up via WhatsApp"],"urgencyLevel":"Média","fitScore":71,"summary":"Academia com presença digital básica mas sem estratégia. Bom potencial para serviços de performance e automação."}',
    '{"instagram":"@fitforceacademia","facebook":"fitforceacademia","linkedin":"fitforce-academia"}'
),
(
    'lead_004',
    'tenant_demo_001',
    'Advocacia Silva & Associados',
    'Jurídico',
    'https://silvaadvocacia.adv.br',
    '(11) 3214-8765',
    'Rua da Consolação, 2000, Consolação, São Paulo - SP',
    'new',
    74,
    69,
    '{"priorityScore":74,"digitalMaturity":"Média","diagnosis":["Site institucional sem geração de leads","LinkedIn sem conteúdo há 6 meses","Sem anúncios no Google para captação"],"opportunities":["Site com formulário de contato e WhatsApp","Estratégia de conteúdo jurídico no LinkedIn","Google Ads para busca por serviços jurídicos"],"urgencyLevel":"Média","fitScore":69,"summary":"Escritório com presença digital básica mas sem estratégia de captação. Segmento jurídico tem alto ticket médio."}',
    '{"instagram":"","facebook":"silvaadvocacia","linkedin":"silva-associados"}'
),
(
    'lead_005',
    'tenant_demo_001',
    'Pet Shop Patinhas',
    'Pet Care',
    'https://petshoppatinhas.com.br',
    '(11) 97654-3210',
    'Rua dos Pinheiros, 800, Pinheiros, São Paulo - SP',
    'new',
    55,
    58,
    NULL,
    '{"instagram":"@patinhaspetshop","facebook":"patinhaspetshop","linkedin":""}'
),
(
    'lead_006',
    'tenant_demo_001',
    'Construtora Horizonte',
    'Construção Civil',
    '',
    '(11) 3333-4444',
    'Av. Faria Lima, 3000, Itaim Bibi, São Paulo - SP',
    'closed_won',
    88,
    85,
    '{"priorityScore":88,"digitalMaturity":"Baixa","diagnosis":["Sem presença digital","Depende 100% de indicações","Sem portfólio online"],"opportunities":["Site com portfólio e captação","LinkedIn para B2B","Campanha de obras concluídas"],"urgencyLevel":"Alta","fitScore":85,"summary":"Construtora sem presença digital em mercado B2B altíssimo ticket. Cliente convertido."}',
    '{"instagram":"","facebook":"","linkedin":""}'
);

-- Sample followups
INSERT OR IGNORE INTO followups (id, tenant_id, lead_id, title, description, scheduled_at) VALUES (
    'followup_001',
    'tenant_demo_001',
    'lead_001',
    'Ligação de proposta',
    'Apresentar proposta de gestão de redes sociais + tráfego pago',
    datetime('now', '+2 days')
),
(
    'followup_002',
    'tenant_demo_001',
    'lead_002',
    'Reunião diagnóstico',
    'Apresentar análise completa da presença digital e proposta de pacote completo',
    datetime('now', '+1 day')
);

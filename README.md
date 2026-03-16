# ISP Management System

A comprehensive ISP/WISP billing and network management system similar to Splynx.

## 🎯 Core Features

### Network Infrastructure Management
- **OLT Management** - Full support for BDCOM, Huawei, and HSGQ OLTs
  - ONT provisioning, monitoring, and control
  - Signal strength monitoring and historical graphs
  - Remote ONT enable/disable and reboot
  - Auto-discovery of connected ONUs
- **MikroTik Router Management**
  - RouterOS API integration for configuration and monitoring
  - PPPoE server management
  - Hotspot user management
  - Bandwidth queue and firewall rule management
- **Switch Management**
  - Support for Cisco, BDCOM, Huawei, and HSGQ switches
  - Port monitoring and status
  - VLAN configuration
  - PoE management (where supported)
- **Network Topology Visualization (NOC Map)**
  - Interactive drag-and-drop network map
  - Automatic device positioning by type (OLT → MikroTik → Switch → Router)
  - Manual cable drawing with fiber/copper/wifi styling
  - Real-time device status indicators (online/offline)
  - Cable labeling and editing capabilities
  - Responsive design with mobile touch support

### Monitoring & Alerts
- **SNMP Monitoring**
  - Real-time polling of network devices
  - Interface traffic monitoring (in/out bandwidth)
  - Device health monitoring (CPU, memory, temperature)
  - Custom OID monitoring capabilities
- **Alerting System**
  - Configurable thresholds for all monitored metrics
  - Email and SMS notifications
  - Alert acknowledgment and escalation
  - Historical alert tracking and reporting
  - Auto-resolved alerts based on recovery

### Customer & Subscriber Management
- **PPPoE User Management**
  - User creation, modification, and suspension
  - Bandwidth profile assignment
  - Session monitoring and statistics
  - MAC address binding
- **Hotspot Portal**
  - Multiple authentication methods:
    - Username/Password
    - SMS OTP verification
    - Printed vouchers
    - PIN codes
    - Social media login (Facebook, Google)
  - Customizable captive portal pages
  - Bandwidth management per user
  - Session timeout and idle detection
- **Customer Portal**
  - Self-service account management
  - Invoice viewing and payment
  - Usage statistics and graphs
  - Support ticket submission
  - Profile and service management

### Billing & Finance
- **Invoice Management**
  - Automatic invoice generation based on billing cycles
  - Pro-rated billing for mid-cycle changes
  - Tax calculations (VAT, TSC, GST)
  - Multiple currency support
  - PDF invoice generation and email delivery
- **Payment Processing**
  - Integrated payment gateways:
    - eSewa
    - Khalti
    - Stripe (configurable)
    - Bank transfer (manual)
  - Wallet system for prepaid customers
  - Automatic payment reconciliation
  - Late fee and penalty calculations
- **Financial Reporting**
  - Revenue reports (daily, monthly, yearly)
  - Tax liability reports (VAT/TSC)
  - Aging reports (receivables/payables)
  - Profit and loss statements
  - Custom report builder

### Sales & Marketing
- **Leads Management**
  - Lead capture from multiple sources (web, referral, call)
  - Lead scoring and qualification
  - Sales pipeline visualization
  - Conversion tracking to customers
  - Automated follow-up reminders
- **Campaign Tracking**
  - Marketing campaign attribution
  - ROI calculation for marketing spend
  - Customer acquisition cost tracking

### Support & Ticketing
- **Help Desk System**
  - Ticket creation via web, email, and API
  - Priority levels (Low, Medium, High, Critical)
  - SLA tracking and escalation
  - Internal notes and collaboration
  - Knowledge base integration
  - Customer portal for ticket tracking
- **Knowledge Base**
  - Searchable documentation
  - Troubleshooting guides
  - FAQ management
  - Article versioning

### Administration & Security
- **User Management**
  - Role-based access control (RBAC)
  - Custom permission profiles
  - Password policy enforcement
  - Two-factor authentication (2FA)
  - Session management and timeout
- **System Configuration**
  - Centralized settings management
  - Backup and restore functionality
  - System health monitoring
  - Audit logging of all administrative actions
  - Database optimization tools
- **Security Features**
  - IP-based access restrictions
  - Brute force protection
  - SSL/TLS enforcement
  - Regular security updates
  - GDPR compliance tools

### Reporting & Analytics
- **Built-in Reports**
  - Network utilization reports
  - Customer churn and retention analysis
  - ARPU (Average Revenue Per User) tracking
  - Bandwidth usage by customer/package
  - Support ticket trends and resolution times
  - Financial performance dashboards
- **Export Capabilities**
  - CSV/Excel export for all reports
  - Scheduled email reports
  - API access for custom reporting
  - Graph generation (Chart.js integration)

### Integration & Extensibility
- **API Endpoints**
  - RESTful API for third-party integrations
  - Webhook support for real-time events
  - Device provisioning APIs
  - Customer synchronization APIs
- **Plugin Architecture**
  - Modular design for custom features
  - Hook system for extending functionality
  - Template override system for UI customization
- **Multi-tenancy**
  - Support for multiple ISP instances
  - Brand customization per tenant
  - Isolated data storage per tenant

### Technical Stack
- **Backend**: PHP 8+ with MySQL 8+
- **Frontend**: Bootstrap 5, jQuery, Chart.js, Font Awesome
- **Architecture**: MVC pattern with separation of concerns
- **Security**: Prepared statements, input validation, CSRF protection
- **Performance**: Caching mechanisms, lazy loading, asset minification
- **Deployment**: Docker-ready, XAMPP/WAMP compatible, Linux server optimized

## 📊 System Capacity
- Supports 10,000+ concurrent users
- Handles 100,000+ devices in network topology
- Processes 1,000+ invoices per hour
- Scales vertically/horizontally with load balancer support

## 🌐 Supported Devices & Protocols
- **OLT**: BDCOM P3310/P3608, Huawei MA5600/MA5800, HSGQ
- **Routers**: MikroTik RouterOS (all versions)
- **Switches**: Cisco Catalyst/ISR, BDCOM, Huawei, HSGQ
- **Protocols**: SNMP v1/v2c/v3, TR-069, RADIUS, DHCP, PPPoE
- **Database**: MySQL/MariaDB, PostgreSQL (experimental)

## 🔧 Installation & Setup
- One-click installer script available
- Docker Compose for containerized deployment
- Detailed documentation with screenshots
- Video tutorials for common tasks
- Community forum and professional support options

--- 

*Feature list updated as of version 2.0.0 - Continuously evolving with community feedback!*
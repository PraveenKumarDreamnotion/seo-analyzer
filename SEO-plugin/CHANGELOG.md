# Changelog

All notable changes to the SEO Analyzer plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.0

### Changed
- New check engine: 33 scored checks in four groups, plus 4 education extras.
- Thresholds follow the Halo SEO, GEO and AEO standard.
- A check that cannot run shows "Not measured" and is left out of the score.
- Grades A to F replace the eight-tier label.
- Endpoints moved from `admin-ajax.php` to the REST API under `/wp-json/halo-audit/v1/`.
- Reports are stored on the server. The email gate is enforced on the server.
- The PDF is built on the server from the stored report.
- Admin reports list is paginated. Lead export is one CSV.
- Needs PHP 8.0 and WordPress 6.2.

### Added
- AI-readiness group: AI crawler access, llms.txt, schema types, FAQ schema.
- Limits on audits, email codes and downloads.
- A slot for the live AI visibility scan.
- Unit and integration tests.

### Removed
- Competitor comparison.
- OpenAI and Google NLP add-ons.
- Bundled TCPDF copy and other unused code.

### Security
- Several fixes. Update as soon as possible.


## [1.0.0] - 2024-01-03

### Added
- Initial plugin release
- Basic SEO analysis functionality
- WordPress integration
- Admin interface
- Basic reporting features
- OpenAI integration support
- SEO analysis features
- Public API endpoints
- Custom shortcode functionality
- Database management system
- User authentication and OTP verification
- AJAX handlers for asynchronous operations
- PDF report generation capability

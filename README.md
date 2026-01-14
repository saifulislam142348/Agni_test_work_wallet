# Wallet Integration w/ bKash Tokenized Checkout

## 1. Project Overview
This project implements a resilient, production-grade digital wallet system integrated with **bKash Tokenized Checkout**. It features atomic transactions, idempotency via Redis locking, automated payment reconciliation, and high-fidelity PDF statement generation using Gotenberg.

The system is designed to handle high-concurrency payment scenarios, ensuring financial data integrity through double-entry accounting principles (Credits/Debits) and strict user scoping.

---

## 2. Architecture & Tech Stack

### Core Stack
- **Backend**: Laravel 11/12 (PHP 8.2+)
- **Frontend**: Vue.js 3 + Tailwind CSS (SPA/Hybrid)
- **Database**: MySQL 8.0 (Transactional Storage)
- **Cache/Locks**: Redis (via Docker)
- **PDF Engine**: Gotenberg (via Docker)
- **Web Server**: Nginx/Apache

### Architectural Principles

#### 1. Atomic Transactions & Double-Entry
Financial integrity is paramount. Every wallet modification happens within a database transaction.
- **Credit**: Increase Balance, Log Transaction (Type: Credit)
- **Debit**: Decrease Balance, Log Transaction (Type: Debit)
If any step fails, the entire transaction rolls back.

#### 2. Idempotency & Concurrency Control (Redis Locks)
To prevent double-spending or duplicate charges (e.g., user double-clicking "Pay"):
- **Strategy**: `SETNX` (Set if Not Exists) with a TTL (Time-To-Live).
- **Flow**:
  1. User initiates payment.
  2. System attempts to acquire a lock key: `lock:payment:{user_id}`.
  3. **If Acquired**: Proceed with bKash API call and DB update. Release lock upon completion.
  4. **If Failed**: Reject request immediately (429 Too Many Requests or 409 Conflict).

#### 3. Secure Agreement Binding
- The `agreementId` returned by bKash is the "master key" for future charges.
- **Storage**: Saved in the `agreements` table, strictly linked to the `user_id`.
- **Security**: The agreement ID is considered sensitive. In a full production env, this would be encrypted at rest using Laravel's `Crypt` facade.

#### 4. Automated Reconciliation
The system ensures the internal wallet balance matches the bKash transaction history.
- **Verification**: Post-payment logic cross-checks the bKash `trxID` and `status` before finalizing the internal wallet credit.

---

## 3. Functional Requirements

### 1. Identity & Localization
- **Authentication**: Laravel Sanctum handles secure, stateful API authentication.
- **Localization**:
  - Middleware checks `Accept-Language` header or Session.
  - Toggles between English (`en`) and Bangla (`bn`).
  - Frontend persists preference in LocalStorage/Cookies.

### 2. Agreement Binding Flow (Link Wallet)
1. **Initiate**: User clicks "Link Wallet".
2. **Create Agreement**: Call bKash `createAgreement` API.
3. **Redirect**: User is redirected to bKash Payment Gateway.
4. **Callback**: Upon success, bKash redirects to our `callback` URL.
5. **Execute**: System calls `step_executeAgreement` to finalize and receive the `agreementId`.
6. **Store**: `agreementId` is stored in DB.

### 3. Payment With Agreement (Add Money)
Allows charging the user *without* OTP.
1. **Request**: User requests Amount: 500 BDT.
2. **Lock**: Acquire Redis Lock.
3. **API Call**: `createPayment` -> `executePayment` using the stored `agreementId`.
4. **Validation**: Verify `trxID` provided by bKash.
5. **Update**: Atomic DB transaction to Credit Wallet using `WalletService`.
6. **Release**: Release Redis Lock.

### 4. Refund Logic
- **Constraint**: Refunds require `paymentId` and `trxId`.
- **Validation**: Cannot refund more than the original transaction amount.
- **Internal**: Atomic Debit from Wallet Balance to reflect the refund sent back to the user's mobile wallet.

### 5. History & Statements
- **UI**: Paginated list of all Credits, Debits, and Refunds.
- **PDF**:
  - **No DOMPDF**. We use **Gotenberg** (a stateless API for Chrome-based PDF generation).
  - **Process**: Laravel renders a Blade view -> Sends HTML to Gotenberg Container -> Receives PDF Stream -> Browser Download.

---

## 4. Database Schema

### `wallets`
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt | PK |
| `user_id` | BigInt | Owner of the wallet |
| `balance` | Decimal(15,2) | Current available funds |
| `currency` | String | Default 'BDT' |

### `agreements`
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt | PK |
| `user_id` | BigInt | Owner |
| `agreement_id` | String | bKash Agreement Token |
| `payer_reference` | String | Masked Phone Number |
| `status` | String | Active/Cancelled |

### `transactions`
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt | PK |
| `wallet_id` | BigInt | FK |
| `amount` | Decimal(15,2) | Transaction Amount |
| `type` | Enum | `credit`, `debit`, `refund` |
| `trx_id` | String | External Transaction ID (bKash TrxID) |
| `reference_id` | String | Payment ID / Refund ID |
| `balance_after` | Decimal(15,2) | Balance snapshot after tx |

---

## 5. Deployment & Setup

### Prerequisites
- Docker & Docker Compose
- PHP 8.2+ & Composer
- Node.js & NPM

### Installation Steps

1. **Clone & Install Dependencies**
   ```bash
   git clone <repo_url>
   cd wallet-integration
   composer install
   npm install
   ```

2. **Environment Configuration**
   Copy `.env.example` to `.env` and set:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=wallet

   # Redis Configuration
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379

   # Gotenberg
   GOTENBERG_URL=http://localhost:3000

   # bKash Credentials
   BKASH_APP_KEY=...
   BKASH_APP_SECRET=...
   BKASH_USERNAME=...
   BKASH_PASSWORD=...
   BKASH_BASE_URL=https://tokenized.sandbox.bka.sh/v1.2.0-beta
   ```

3. **Infrastructure (Docker)**
   Start Redis and Gotenberg:
   ```bash
   docker-compose up -d
   ```

4. **Database Setup**
   ```bash
   php artisan migrate --seed
   ```

5. **Run Application**
   ```bash
   npm run dev
   php artisan serve
   ```

### Verification (Testing)
1. **Link Wallet**: Go to Dashboard -> Click "Link Wallet". Use Test Number `01770618575`, OTP `123456`, PIN `12121`.
2. **Add Money**: Click "Add Money" -> Enter Amount -> Confirm. Verify Balance updates.
3. **Double Click Test**: Quickly double-click "Pay". Check Network tab; 2nd request should fail (429/409).
4. **Statement**: Click "Download Statement". Verify PDF generation via Gotenberg.

---

## 6. Security & Best Practices
- **Secrets**: bKash App Secret and Username/Password are stored in `.env`, never committed.
- **Validation**: All inputs (Amount, Phone) are strictly validated.
- **Sanitization**: Output escaping to prevent XSS in History/Statements.
- **Fail-Safe**: Database transactions ensure no "money lost" scenarios during exceptions.
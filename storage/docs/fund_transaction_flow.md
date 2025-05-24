
---

# 💰 Fund Flow Overview

This document outlines how user funds are managed within the application. It details both **positive** (credit) and **negative** (debit) fund operations along with the appropriate controller methods and rollback handling.

## ✅ Positive Fund Flows

These operations **increase** the user's fund balance. Each action is subject to validation and may trigger a rollback in case of failure.

| Action              | Description                     | Controller Method                                | Rollback Handling |
| ------------------- | ------------------------------- | ------------------------------------------------ | ----------------- |
| **Deposit**         | User adds money to wallet       | `TransactionController::fundApproval()`          | ✅ Yes             |
| **Quiz Reward**     | User receives reward after quiz | `QuizController::leaderboard()`                  | ✅ Yes             |
| **Referral Reward** | Reward for referring a user     | `PlayQuizController::claimReferalRewardAmount()` | ✅ Yes             |

---

## ❌ Negative Fund Flows

These operations **decrease** the user's fund balance. All debits ensure rollback safety if a transaction fails at any point.

| Action                | Description                      | Controller Method                        | Rollback Handling |
| --------------------- | -------------------------------- | ---------------------------------------- | ----------------- |
| **Withdraw**          | User withdraws money from wallet | `TransactionController::fundApproval()`  | ✅ Yes             |
| **Quiz Entry**        | Fee to enter a quiz              | `PlayQuizController::join_quiz()`        | ✅ Yes             |
| **Lifeline Purchase** | Buying a quiz lifeline           | `LifelineController::purchaseLifeline()` | ✅ Yes             |

---

## ♻️ Rollback Flow

All fund-related actions are wrapped with **transaction-safe mechanisms**. In case of failure (e.g., network error, validation fail, database issue), a `rollBack()` is triggered to maintain **fund consistency** and **data integrity**.

```php
DB::beginTransaction();
try {
    // Perform fund update operations
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // Handle failure (e.g., log, notify, return error)
}
```

---

## 🔒 Best Practices

* Always wrap fund operations in a DB transaction.
* Use centralized methods for fund updates to ensure consistent logging and rollback.
* Maintain a fund ledger or transaction log for auditing and debugging.

---

Let me know if you'd like to include examples, diagrams, or extend this to show test coverage or logs.

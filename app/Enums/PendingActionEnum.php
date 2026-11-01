<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PendingActionEnum: string
{
    case IssueInvoice = 'issue_invoice';
    case SendInvoice = 'send_invoice';
    case SendQuote = 'send_quote';
    case SignContract = 'sign_contract';
    case CancelInvoice = 'cancel_invoice';
    case DeleteInvoiceDraft = 'delete_invoice_draft';
    case DeleteQuote = 'delete_quote';
    case DeleteTask = 'delete_task';
    case DeleteExpense = 'delete_expense';

    /** @return class-string<Model> */
    public function subjectClass(): string
    {
        return match ($this) {
            self::IssueInvoice, self::SendInvoice, self::CancelInvoice, self::DeleteInvoiceDraft => Invoice::class,
            self::SendQuote, self::DeleteQuote => Quote::class,
            self::SignContract => Contract::class,
            self::DeleteTask => Task::class,
            self::DeleteExpense => Expense::class,
        };
    }

    /** Policy ability checked on the subject, both at prepare and at execute. */
    public function ability(): string
    {
        return match ($this) {
            self::IssueInvoice => 'issue',
            self::SendInvoice, self::SendQuote => 'send',
            self::SignContract => 'sign',
            self::CancelInvoice => 'cancel',
            self::DeleteInvoiceDraft, self::DeleteQuote, self::DeleteTask, self::DeleteExpense => 'delete',
        };
    }

    public function label(): string
    {
        return __('app.pending_action_'.$this->value);
    }
}

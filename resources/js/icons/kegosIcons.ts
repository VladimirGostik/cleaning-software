import type { Component } from 'vue';
import DashboardOutline from '~icons/material-symbols/dashboard-outline-rounded';
import DashboardFilled from '~icons/material-symbols/dashboard-rounded';
import ApartmentOutline from '~icons/material-symbols/apartment-outline-rounded';
import ApartmentFilled from '~icons/material-symbols/apartment-rounded';
import BadgeOutline from '~icons/material-symbols/badge-outline-rounded';
import BadgeFilled from '~icons/material-symbols/badge-rounded';
import GroupsOutline from '~icons/material-symbols/groups-outline-rounded';
import GroupsFilled from '~icons/material-symbols/groups-rounded';
import RequestQuoteOutline from '~icons/material-symbols/request-quote-outline-rounded';
import RequestQuoteFilled from '~icons/material-symbols/request-quote-rounded';
import ReceiptLongOutline from '~icons/material-symbols/receipt-long-outline-rounded';
import ReceiptLongFilled from '~icons/material-symbols/receipt-long-rounded';
import AutorenewOutline from '~icons/material-symbols/autorenew-outline-rounded';
import AutorenewFilled from '~icons/material-symbols/autorenew-rounded';
import TaskOutline from '~icons/material-symbols/task-outline-rounded';
import TaskFilled from '~icons/material-symbols/task-rounded';
import DescriptionOutline from '~icons/material-symbols/description-outline-rounded';
import DescriptionFilled from '~icons/material-symbols/description-rounded';
import CalendarMonthOutline from '~icons/material-symbols/calendar-month-outline-rounded';
import CalendarMonthFilled from '~icons/material-symbols/calendar-month-rounded';
import CheckCircleOutline from '~icons/material-symbols/check-circle-outline-rounded';
import CheckCircleFilled from '~icons/material-symbols/check-circle-rounded';
import PaymentsOutline from '~icons/material-symbols/payments-outline-rounded';
import PaymentsFilled from '~icons/material-symbols/payments-rounded';
import NotificationsOutline from '~icons/material-symbols/notifications-outline-rounded';
import NotificationsFilled from '~icons/material-symbols/notifications-rounded';
import NotificationsActiveOutline from '~icons/material-symbols/notifications-active-outline-rounded';
import NotificationsActiveFilled from '~icons/material-symbols/notifications-active-rounded';
import GppMaybeOutline from '~icons/material-symbols/gpp-maybe-outline-rounded';
import GppMaybeFilled from '~icons/material-symbols/gpp-maybe-rounded';
import ManageAccountsOutline from '~icons/material-symbols/manage-accounts-outline-rounded';
import ManageAccountsFilled from '~icons/material-symbols/manage-accounts-rounded';
import AdminPanelSettingsOutline from '~icons/material-symbols/admin-panel-settings-outline-rounded';
import AdminPanelSettingsFilled from '~icons/material-symbols/admin-panel-settings-rounded';
import HistoryOutline from '~icons/material-symbols/history-outline-rounded';
import HistoryFilled from '~icons/material-symbols/history-rounded';
import PhotoLibraryOutline from '~icons/material-symbols/photo-library-outline-rounded';
import PhotoLibraryFilled from '~icons/material-symbols/photo-library-rounded';
import PersonOutline from '~icons/material-symbols/person-outline-rounded';
import PersonFilled from '~icons/material-symbols/person-rounded';
import SettingsOutline from '~icons/material-symbols/settings-outline-rounded';
import SettingsFilled from '~icons/material-symbols/settings-rounded';
import EditOutline from '~icons/material-symbols/edit-outline-rounded';
import DeleteOutline from '~icons/material-symbols/delete-outline-rounded';
import SearchRounded from '~icons/material-symbols/search-rounded';
import CloseRounded from '~icons/material-symbols/close-rounded';
import ArrowUpward from '~icons/material-symbols/arrow-upward-rounded';
import ArrowDownward from '~icons/material-symbols/arrow-downward-rounded';
import UnfoldMore from '~icons/material-symbols/unfold-more-rounded';
import LinkRounded from '~icons/material-symbols/link-rounded';
import ImageOutline from '~icons/material-symbols/image-outline-rounded';
import EventRounded from '~icons/material-symbols/event-rounded';
import TimelapseRounded from '~icons/material-symbols/timelapse-rounded';
import CheckCircleRounded from '~icons/material-symbols/check-circle-rounded';
import WarningRounded from '~icons/material-symbols/warning-rounded';
import ErrorRounded from '~icons/material-symbols/error-rounded';
import EditNoteRounded from '~icons/material-symbols/edit-note-rounded';
import BlockRounded from '~icons/material-symbols/block';
import FilterAltOutline from '~icons/material-symbols/filter-alt-outline-rounded';
import MoreHorizRounded from '~icons/material-symbols/more-horiz-rounded';
import ChatBubbleOutline from '~icons/material-symbols/chat-bubble-outline-rounded';
import AttachFileRounded from '~icons/material-symbols/attach-file-rounded';
import KeyboardArrowDownRounded from '~icons/material-symbols/keyboard-arrow-down-rounded';
import TableRowsOutline from '~icons/material-symbols/table-rows-outline-rounded';
import ViewKanbanOutline from '~icons/material-symbols/view-kanban-outline-rounded';
import AddRounded from '~icons/material-symbols/add-rounded';
import CheckRounded from '~icons/material-symbols/check-rounded';

interface NavIcon {
    outline: Component;
    filled: Component;
}

export const NAV_ICONS: Record<App.Enums.NavIconEnum, NavIcon> = {
    dashboard: { outline: DashboardOutline, filled: DashboardFilled },
    buildings: { outline: ApartmentOutline, filled: ApartmentFilled },
    team: { outline: BadgeOutline, filled: BadgeFilled },
    clients: { outline: GroupsOutline, filled: GroupsFilled },
    quotes: { outline: RequestQuoteOutline, filled: RequestQuoteFilled },
    invoices: { outline: ReceiptLongOutline, filled: ReceiptLongFilled },
    recurring_invoices: { outline: AutorenewOutline, filled: AutorenewFilled },
    contracts: { outline: TaskOutline, filled: TaskFilled },
    documents: { outline: DescriptionOutline, filled: DescriptionFilled },
    schedule: { outline: CalendarMonthOutline, filled: CalendarMonthFilled },
    tasks: { outline: CheckCircleOutline, filled: CheckCircleFilled },
    expenses: { outline: PaymentsOutline, filled: PaymentsFilled },
    notifications: { outline: NotificationsOutline, filled: NotificationsFilled },
    notification_settings: { outline: NotificationsActiveOutline, filled: NotificationsActiveFilled },
    pending_actions: { outline: GppMaybeOutline, filled: GppMaybeFilled },
    users: { outline: ManageAccountsOutline, filled: ManageAccountsFilled },
    roles: { outline: AdminPanelSettingsOutline, filled: AdminPanelSettingsFilled },
    audit_logs: { outline: HistoryOutline, filled: HistoryFilled },
    media: { outline: PhotoLibraryOutline, filled: PhotoLibraryFilled },
    profile: { outline: PersonOutline, filled: PersonFilled },
    invoice_settings: { outline: SettingsOutline, filled: SettingsFilled },
    settings: { outline: SettingsOutline, filled: SettingsFilled },
};

export const KG_ICONS = {
    status: {
        planned: EventRounded,
        inProgress: TimelapseRounded,
        done: CheckCircleRounded,
        warning: WarningRounded,
        danger: ErrorRounded,
        draft: EditNoteRounded,
        cancelled: BlockRounded,
    },
    adminAction: {
        edit: EditOutline,
        delete: DeleteOutline,
        search: SearchRounded,
        close: CloseRounded,
        sortAsc: ArrowUpward,
        sortDesc: ArrowDownward,
        sortNone: UnfoldMore,
        link: LinkRounded,
        image: ImageOutline,
        filter: FilterAltOutline,
        more: MoreHorizRounded,
        caretDown: KeyboardArrowDownRounded,
        add: AddRounded,
        check: CheckRounded,
    },
    task: {
        comment: ChatBubbleOutline,
        attachment: AttachFileRounded,
        viewTable: TableRowsOutline,
        viewBoard: ViewKanbanOutline,
    },
};

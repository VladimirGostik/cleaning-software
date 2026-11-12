import type { Component } from 'vue';
import DashboardOutline from '~icons/material-symbols/dashboard-outline-rounded';
import ApartmentOutline from '~icons/material-symbols/apartment-outline-rounded';
import BadgeOutline from '~icons/material-symbols/badge-outline-rounded';
import GroupsOutline from '~icons/material-symbols/groups-outline-rounded';
import RequestQuoteOutline from '~icons/material-symbols/request-quote-outline-rounded';
import ReceiptLongOutline from '~icons/material-symbols/receipt-long-outline-rounded';
import AutorenewOutline from '~icons/material-symbols/autorenew-outline-rounded';
import TaskOutline from '~icons/material-symbols/task-outline-rounded';
import DescriptionOutline from '~icons/material-symbols/description-outline-rounded';
import CalendarMonthOutline from '~icons/material-symbols/calendar-month-outline-rounded';
import CheckCircleOutline from '~icons/material-symbols/check-circle-outline-rounded';
import PaymentsOutline from '~icons/material-symbols/payments-outline-rounded';
import NotificationsOutline from '~icons/material-symbols/notifications-outline-rounded';
import NotificationsActiveOutline from '~icons/material-symbols/notifications-active-outline-rounded';
import GppMaybeOutline from '~icons/material-symbols/gpp-maybe-outline-rounded';
import ManageAccountsOutline from '~icons/material-symbols/manage-accounts-outline-rounded';
import AdminPanelSettingsOutline from '~icons/material-symbols/admin-panel-settings-outline-rounded';
import HistoryOutline from '~icons/material-symbols/history-outline-rounded';
import PhotoLibraryOutline from '~icons/material-symbols/photo-library-outline-rounded';
import PersonOutline from '~icons/material-symbols/person-outline-rounded';
import SettingsOutline from '~icons/material-symbols/settings-outline-rounded';
import EditOutline from '~icons/material-symbols/edit-outline-rounded';
import DeleteOutline from '~icons/material-symbols/delete-outline-rounded';
import SearchOutline from '~icons/material-symbols/search-outline-rounded';
import CloseOutline from '~icons/material-symbols/close-outline-rounded';
import ArrowUpward from '~icons/material-symbols/arrow-upward-outline-rounded';
import ArrowDownward from '~icons/material-symbols/arrow-downward-outline-rounded';
import UnfoldMore from '~icons/material-symbols/unfold-more-outline-rounded';
import LinkOutline from '~icons/material-symbols/link-outline-rounded';
import ImageOutline from '~icons/material-symbols/image-outline-rounded';
import FilterAltOutline from '~icons/material-symbols/filter-alt-outline-rounded';
import MoreHorizOutline from '~icons/material-symbols/more-horiz-outline-rounded';
import ChatBubbleOutline from '~icons/material-symbols/chat-bubble-outline-rounded';
import AttachFileOutline from '~icons/material-symbols/attach-file-outline-rounded';
import KeyboardArrowDownOutline from '~icons/material-symbols/keyboard-arrow-down-outline-rounded';
import TableRowsOutline from '~icons/material-symbols/table-rows-outline-rounded';
import ViewKanbanOutline from '~icons/material-symbols/view-kanban-outline-rounded';
import AddOutline from '~icons/material-symbols/add-outline-rounded';
import CheckOutline from '~icons/material-symbols/check-outline-rounded';

export const NAV_ICONS: Record<App.Enums.NavIconEnum, Component> = {
    dashboard: DashboardOutline,
    buildings: ApartmentOutline,
    team: BadgeOutline,
    clients: GroupsOutline,
    quotes: RequestQuoteOutline,
    invoices: ReceiptLongOutline,
    recurring_invoices: AutorenewOutline,
    contracts: TaskOutline,
    documents: DescriptionOutline,
    schedule: CalendarMonthOutline,
    tasks: CheckCircleOutline,
    expenses: PaymentsOutline,
    notifications: NotificationsOutline,
    notification_settings: NotificationsActiveOutline,
    pending_actions: GppMaybeOutline,
    users: ManageAccountsOutline,
    roles: AdminPanelSettingsOutline,
    audit_logs: HistoryOutline,
    media: PhotoLibraryOutline,
    profile: PersonOutline,
    invoice_settings: SettingsOutline,
    settings: SettingsOutline,
};

export const KG_ICONS = {
    adminAction: {
        edit: EditOutline,
        delete: DeleteOutline,
        search: SearchOutline,
        close: CloseOutline,
        sortAsc: ArrowUpward,
        sortDesc: ArrowDownward,
        sortNone: UnfoldMore,
        link: LinkOutline,
        image: ImageOutline,
        filter: FilterAltOutline,
        more: MoreHorizOutline,
        caretDown: KeyboardArrowDownOutline,
        add: AddOutline,
        check: CheckOutline,
    },
    task: {
        comment: ChatBubbleOutline,
        attachment: AttachFileOutline,
        viewTable: TableRowsOutline,
        viewBoard: ViewKanbanOutline,
    },
};

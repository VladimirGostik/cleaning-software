export function mobileNavItems(navigation: App.Data.NavigationItemData[], limit = 4): App.Data.NavigationItemData[] {
    return navigation.filter((item) => item.children.length === 0).slice(0, limit);
}

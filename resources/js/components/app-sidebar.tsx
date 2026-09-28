import { Link, usePage } from '@inertiajs/react';
import { Building2, LayoutGrid, ShieldCheck, Users } from 'lucide-react';
import AdminUserController from '@/actions/App/Http/Controllers/Admin/UserController';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;

    const crmNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ...(auth.can.viewCompanies
            ? [
                  {
                      title: 'Companies',
                      href: CompanyController.index(),
                      icon: Building2,
                  },
              ]
            : []),
        ...(auth.can.viewContacts
            ? [
                  {
                      title: 'Contacts',
                      href: ContactController.index(),
                      icon: Users,
                  },
              ]
            : []),
    ];

    const adminNavItems: NavItem[] = [
        {
            title: 'Users',
            href: AdminUserController.index(),
            icon: ShieldCheck,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="CRM" items={crmNavItems} />
                {auth.can.manageUsers && (
                    <NavMain label="Administration" items={adminNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

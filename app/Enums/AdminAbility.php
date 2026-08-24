<?php

namespace App\Enums;

enum AdminAbility: string
{
    case Dashboard = 'dashboard.view';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case BillingRead = 'billing.read';
    case CustomersRead = 'customers.read';
    case CustomersManage = 'customers.manage';
    case ProjectsRead = 'projects.read';
    case ProjectsManage = 'projects.manage';
    case TicketsManage = 'tickets.manage';
    case IncidentsManage = 'incidents.manage';
    case InfrastructureRead = 'infrastructure.read';
    case InfrastructureManage = 'infrastructure.manage';
    case NetworkManage = 'network.manage';
    case ResellersRead = 'resellers.read';
    case ResellersManage = 'resellers.manage';
    case PromotionsManage = 'promotions.manage';
    case SettingsManage = 'settings.manage';
    case PricingManage = 'pricing.manage';
    case ApiActivityView = 'api-activity.view';
}

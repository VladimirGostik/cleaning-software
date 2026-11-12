<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Data\NavigationItemData;
use App\Enums\NavIconEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NavigationRegistry
{
    /**
     * Synthetic group parents. Children NavItems set `group` to one of these keys.
     *
     * @var array<string, array{label: string, icon: NavIconEnum, order: int}>
     */
    public const GROUPS = [
        'settings' => ['label' => 'app.settings', 'icon' => NavIconEnum::Settings, 'order' => 9000],
    ];

    /** @var array<int, array{attr: NavItem, route: Route}>|null */
    private ?array $discovered = null;

    public function __construct(private readonly Router $router) {}

    /** @return array<int, NavigationItemData> */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $visible = [];
        foreach ($this->discover() as $row) {
            if ($this->canAccess($user, $row['attr'])) {
                $visible[] = $row;
            }
        }

        return $this->buildTree($visible);
    }

    public function landingFor(User $user, string $tenantId, string $redirectTo): string
    {
        $parts = explode('?', $redirectTo, 2);
        $path = '/'.trim($parts[0], '/');
        $query = $parts[1] ?? '';
        $kept = $query === '' ? $path : $path.'?'.$query;

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $this->resolveLanding($user, $path, $kept);
        } finally {
            setPermissionsTeamId($previousTeamId);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    private function resolveLanding(User $user, string $path, string $kept): string
    {
        $best = null;
        foreach ($this->discover() as $row) {
            $href = '/'.ltrim($row['route']->uri(), '/');
            if (str_contains($href, '{')) {
                continue;
            }

            if ($href === $path) {
                return $this->canAccess($user, $row['attr']) ? $kept : '/';
            }

            $isPrefix = $href !== '/' && str_starts_with($path, $href.'/');
            if ($isPrefix && ($best === null || strlen($href) > strlen($best['href']))) {
                $best = ['href' => $href, 'attr' => $row['attr']];
            }
        }

        if ($best !== null) {
            return $this->canAccess($user, $best['attr']) ? $best['href'] : '/';
        }

        return $this->isParameterlessGetRoute($path) ? $kept : '/';
    }

    private function isParameterlessGetRoute(string $path): bool
    {
        try {
            $route = $this->router->getRoutes()->match(Request::create($path));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }

        return $route->parameterNames() === [];
    }

    /** @return array<int, array{attr: NavItem, route: Route}> */
    private function discover(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $rows = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $controller = $route->getControllerClass();
            if ($controller === null) {
                continue;
            }

            $method = $route->getActionMethod();
            if ($method === $controller) {
                $method = '__invoke';
            }

            if (! class_exists($controller) || ! method_exists($controller, $method)) {
                continue;
            }

            $reflection = new ReflectionMethod($controller, $method);
            foreach ($reflection->getAttributes(NavItem::class) as $attribute) {
                $rows[] = [
                    'attr' => $attribute->newInstance(),
                    'route' => $route,
                ];
            }
        }

        return $this->discovered = $rows;
    }

    private function canAccess(User $user, NavItem $item): bool
    {
        if ($item->permission === null) {
            return true;
        }

        if ($item->policyModel !== null) {
            return Gate::forUser($user)->allows($item->permission, $item->policyModel);
        }

        return $user->can($item->permission);
    }

    /**
     * @param  array<int, array{attr: NavItem, route: Route}>  $visible
     * @return array<int, NavigationItemData>
     */
    private function buildTree(array $visible): array
    {
        $tops = [];
        $groups = [];

        foreach ($visible as $row) {
            $attr = $row['attr'];
            $route = $row['route'];
            $href = '/'.ltrim($route->uri(), '/');

            $data = new NavigationItemData(
                key: $route->getName() ?? $route->uri(),
                label: $attr->label,
                href: $href,
                icon: $attr->icon,
                order: $attr->order,
            );

            if ($attr->group === null) {
                $tops[] = $data;

                continue;
            }

            $groups[$attr->group][] = $data;
        }

        foreach ($groups as $groupKey => $children) {
            $meta = self::GROUPS[$groupKey] ?? null;
            if ($meta === null) {
                continue;
            }

            usort($children, fn (NavigationItemData $a, NavigationItemData $b) => $a->order <=> $b->order);

            $tops[] = new NavigationItemData(
                key: "group:{$groupKey}",
                label: $meta['label'],
                href: '',
                icon: $meta['icon'],
                order: $meta['order'],
                children: $children,
            );
        }

        usort($tops, fn (NavigationItemData $a, NavigationItemData $b) => $a->order <=> $b->order);

        return $tops;
    }
}

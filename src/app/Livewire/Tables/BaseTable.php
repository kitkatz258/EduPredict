<?php

namespace App\Livewire\Tables;

use App\Livewire\Concerns\DispatchesToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

abstract class BaseTable extends Component
{
    use DispatchesToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'id')]
    public string $sortField = 'id';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    #[Url(except: 10)]
    public int $perPage = 10;

    /** @var array<string, mixed> */
    public array $filters = [];

    /**
     * @var list<int>
     */
    protected array $perPageOptions = [10, 25, 50];

    abstract protected function baseQuery(): Builder;

    /**
     * @return list<array{key: string, label: string, sortable?: bool, align?: string}>
     */
    abstract protected function columns(): array;

    /**
     * @return list<string>
     */
    abstract protected function searchColumns(): array;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingFilters(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $sortable = collect($this->columns())
            ->filter(fn (array $column): bool => (bool) ($column['sortable'] ?? true))
            ->pluck('key')
            ->all();

        if (! in_array($field, $sortable, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    protected function applyFilters(Builder $query): Builder
    {
        return $query;
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);

        if ($term === '') {
            return $query;
        }

        $columns = $this->searchColumns();

        return $query->where(function (Builder $inner) use ($columns, $term): void {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $inner->where($column, 'like', '%'.$term.'%');
                } else {
                    $inner->orWhere($column, 'like', '%'.$term.'%');
                }
            }
        });
    }

    protected function filteredQuery(): Builder
    {
        $query = $this->applyFilters($this->applySearch($this->baseQuery()));

        $allowed = collect($this->columns())->pluck('key')->all();
        $field = in_array($this->sortField, $allowed, true) ? $this->sortField : 'id';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($field, $direction);
    }

    public function rows(): LengthAwarePaginator
    {
        return $this->filteredQuery()->paginate($this->perPage);
    }

    /**
     * @return array<string, mixed>
     */
    protected function tableViewData(): array
    {
        return [
            'rows' => $this->rows(),
            'columns' => $this->columns(),
            'perPageOptions' => $this->perPageOptions,
            'emptyMessage' => $this->emptyMessage(),
        ];
    }

    protected function emptyMessage(): string
    {
        return 'No records match the current search or filters.';
    }

    public function render(): View
    {
        return view('livewire.tables.table', $this->tableViewData());
    }
}

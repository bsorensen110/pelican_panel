<?php

namespace App\Filament\Server\Pages;

use App\Models\Server;
use App\Services\Mods\ModrinthApiService;
use App\Services\Mods\ModDownloadService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Exception;

class Mods extends ServerFormPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-puzzle';

    protected static ?int $navigationSort = 15;

    public ?string $searchQuery = '';
    public ?array $selectedMods = [];
    public ?array $searchResults = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(trans('server/mods.search.title'))
                    ->description(trans('server/mods.search.description'))
                    ->schema([
                        TextInput::make('searchQuery')
                            ->label(trans('server/mods.search.query'))
                            ->placeholder(trans('server/mods.search.placeholder'))
                            ->default($this->searchQuery)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state) {
                                $this->searchQuery = $state;
                                if (!empty($state)) {
                                    $this->searchMods($state);
                                }
                            }),

                        Select::make('gameVersion')
                            ->label(trans('server/mods.search.version'))
                            ->options([
                                '1.20.1' => '1.20.1',
                                '1.19.4' => '1.19.4',
                                '1.19.2' => '1.19.2',
                                '1.18.2' => '1.18.2',
                                '1.17.1' => '1.17.1',
                                '1.16.5' => '1.16.5',
                            ])
                            ->default('1.20.1')
                            ->live(),

                        Select::make('modLoader')
                            ->label(trans('server/mods.search.loader'))
                            ->options([
                                'forge' => 'Forge',
                                'fabric' => 'Fabric',
                                'quilt' => 'Quilt',
                            ])
                            ->default('forge')
                            ->live(),
                    ]),

                Section::make(trans('server/mods.search.results'))
                    ->description(trans('server/mods.search.select'))
                    ->visible(fn () => !empty($this->searchResults))
                    ->schema([
                        CheckboxList::make('selectedMods')
                            ->label('')
                            ->options($this->getModOptions())
                            ->default($this->selectedMods)
                            ->columns(1)
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->selectedMods = $state;
                            }),
                    ]),

                Section::make(trans('server/mods.download.title'))
                    ->description(trans('server/mods.download.description'))
                    ->visible(fn () => !empty($this->selectedMods))
                    ->footerActions([
                        Action::make('download')
                            ->label(trans('server/mods.download.action'))
                            ->color('success')
                            ->icon('tabler-download')
                            ->action(function () {
                                $this->downloadSelectedMods();
                            }),
                    ])
                    ->footerActionsAlignment(Alignment::Right)
                    ->schema([
                        // Empty schema, just for the action
                    ]),
            ]);
    }

    protected function searchMods(string $query): void
    {
        try {
            $apiService = new ModrinthApiService();

            $options = [
                'limit' => 20,
            ];

            // Add version filter if specified
            $gameVersion = $this->form->getState()['gameVersion'] ?? '1.20.1';
            if ($gameVersion) {
                $options['game_versions'] = [$gameVersion];
            }

            // Add loader filter if specified
            $modLoader = $this->form->getState()['modLoader'] ?? 'forge';
            if ($modLoader) {
                $options['loaders'] = [$modLoader];
            }

            $this->searchResults = $apiService->searchMods($query, $options)->toArray();

        } catch (Exception $e) {
            Notification::make()
                ->title(trans('server/mods.search.failed'))
                ->body('Failed to search mods: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getModOptions(): array
    {
        $options = [];
        foreach ($this->searchResults as $mod) {
            $options[$mod['mod_id']] = $mod['title'] . ' - ' . $mod['description'];
        }
        return $options;
    }

    protected function downloadSelectedMods(): void
    {
        if (empty($this->selectedMods)) {
            Notification::make()
                ->title(trans('server/mods.download.no_selection'))
                ->body(trans('server/mods.download.no_selection_body'))
                ->warning()
                ->send();
            return;
        }

        try {
            $apiService = new ModrinthApiService();
            $downloadService = new ModDownloadService();

            $gameVersion = $this->form->getState()['gameVersion'] ?? '1.20.1';
            $modLoader = $this->form->getState()['modLoader'] ?? 'forge';

            $modsToDownload = [];
            foreach ($this->selectedMods as $modId) {
                $mod = collect($this->searchResults)->firstWhere('mod_id', $modId);
                if ($mod) {
                    // Get the latest version for the selected loader and game version
                    $versions = $apiService->getModVersions($modId, [
                        'loaders' => [$modLoader],
                        'game_versions' => [$gameVersion],
                    ]);

                    if ($versions->isNotEmpty()) {
                        $latestVersion = $versions->first();
                        $downloadUrl = $apiService->getDownloadUrl($latestVersion['id']);

                        $modsToDownload[] = [
                            'url' => $downloadUrl,
                            'filename' => $latestVersion['files'][0]['filename'],
                        ];
                    }
                }
            }

            if (!empty($modsToDownload)) {
                $downloadService->downloadMods($this->getRecord(), $modsToDownload);

                Notification::make()
                    ->title(trans('server/mods.download.started'))
                    ->body(trans('server/mods.download.body'))
                    ->success()
                    ->send();

                // Clear selections
                $this->selectedMods = [];
                $this->searchResults = [];
            } else {
                Notification::make()
                    ->title(trans('server/mods.download.no_versions'))
                    ->body(trans('server/mods.download.no_versions_body'))
                    ->warning()
                    ->send();
            }

        } catch (Exception $e) {
            Notification::make()
                ->title(trans('server/mods.download.failed'))
                ->body('Failed to download mods: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTitle(): string
    {
        return trans('server/mods.title');
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/mods.title');
    }
}
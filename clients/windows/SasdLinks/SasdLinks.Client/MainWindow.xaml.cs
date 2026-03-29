using System.Windows;
using SasdLinks.Client.Services;
using SasdLinks.Client.ViewModels;
using SasdLinks.Core.Services;

namespace SasdLinks.Client;

public partial class MainWindow : Window
{
    private readonly MainViewModel _vm;

    public MainWindow()
    {
        InitializeComponent();

        // "Composition Root" – hier werden Services verdrahtet.
        // Später kannst du das auf DI (Microsoft.Extensions.DependencyInjection) umstellen,
        // aber für Lernbarkeit ist es so am transparentesten.
        ILocalStore store = new LocalJsonStore();

        // Mock API: funktioniert sofort, ohne PHP-Backend
        IApiClient api = new MockApiClient();

        ISyncService sync = new SyncService(store, api);

        _vm = new MainViewModel(store, sync);
        DataContext = _vm;
    }

    private void Links_SelectionChanged(object sender, System.Windows.Controls.SelectionChangedEventArgs e)
    {
        _vm.OnSelectedLinkChanged();
    }
}

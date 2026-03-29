using System.Windows;
using SasdLinks.Client.Services;
using SasdLinks.Client.ViewModels;
using SasdLinks.Core.Services;

namespace SasdLinks.Client;

public partial class MainWindow : Window
{
    public MainWindow()
    {
        InitializeComponent();

        ILocalStore store = new LocalJsonStore();

        // Mock-API funktioniert sofort (remote.json). Später durch HttpApiClient ersetzen.
        IApiClient api = new MockApiClient();

        ISyncService sync = new SyncService(store, api);

        DataContext = new MainViewModel(store, sync);
    }
}

using System.Collections.ObjectModel;
using System.Text;
using System.Windows;
using System.Windows.Data;
using Microsoft.Win32;
using SasdLinks.Client.Services;
using SasdLinks.Core.Models;
using SasdLinks.Core.Services;
using SasdLinks.Core.Validation;

namespace SasdLinks.Client.ViewModels;

public sealed class MainViewModel : ViewModelBase
{
    private readonly ILocalStore _store;
    private readonly ISyncService _sync;

    private Snapshot _snapshot = new();
    private List<PendingOp> _pendingOps = new();

    public ObservableCollection<Project> Projects { get; } = new();
    public ObservableCollection<LinkItem> Links { get; } = new();
    public ObservableCollection<Tag> Tags { get; } = new();
    public ICollectionView LinksView { get; }

    private Project? _selectedProject;
    public Project? SelectedProject
    {
        get => _selectedProject;
        set
        {
            if (Set(ref _selectedProject, value))
                RefreshLinks();
        }
    }

    private LinkItem? _selectedLink;
    public LinkItem? SelectedLink
    {
        get => _selectedLink;
        set
        {
            if (Set(ref _selectedLink, value))
            {
                LoadSelectedLinkToEditor();
                UpdateSelectedLinkTags();
            }
        }
    }

    public ObservableCollection<Tag> SelectedLinkTags { get; } = new();

    private string _searchQuery = "";
    public string SearchQuery
    {
        get => _searchQuery;
        set { if (Set(ref _searchQuery, value)) LinksView.Refresh(); }
    }

    private Tag? _filterTag;
    public Tag? FilterTag
    {
        get => _filterTag;
        set { if (Set(ref _filterTag, value)) LinksView.Refresh(); }
    }

    private string _status = "Ready";
    public string Status { get => _status; set => Set(ref _status, value); }

    private string _newProjectName = "";
    public string NewProjectName { get => _newProjectName; set => Set(ref _newProjectName, value); }

    private string _newTagName = "";
    public string NewTagName { get => _newTagName; set => Set(ref _newTagName, value); }

    private Tag? _tagToAdd;
    public Tag? TagToAdd { get => _tagToAdd; set { if (Set(ref _tagToAdd, value)) AssignTagCommand.RaiseCanExecuteChanged(); } }

    private string _editUrl = "";
    public string EditUrl { get => _editUrl; set => Set(ref _editUrl, value); }

    private string _editTitle = "";
    public string EditTitle { get => _editTitle; set => Set(ref _editTitle, value); }

    private string _editDescription = "";
    public string EditDescription { get => _editDescription; set => Set(ref _editDescription, value); }

    // Commands
    public RelayCommand AddProjectCommand { get; }
    public RelayCommand DeleteProjectCommand { get; }

    public RelayCommand AddTagCommand { get; }
    public RelayCommand DeleteTagCommand { get; }

    public RelayCommand AddLinkCommand { get; }
    public RelayCommand DeleteLinkCommand { get; }
    public RelayCommand SaveLinkCommand { get; }

    public RelayCommand AssignTagCommand { get; }
    public RelayCommand<Tag> RemoveTagCommand { get; }

    public RelayCommand SyncCommand { get; }

    // Menu/Ribbon: Export/Import/Reset/About
    public RelayCommand ExportJsonCommand { get; }
    public RelayCommand ExportCsvCommand { get; }
    public RelayCommand ImportCommand { get; }   // Placeholder
    public RelayCommand ClearSearchCommand { get; }
    public RelayCommand ResetFilterCommand { get; }
    public RelayCommand AboutCommand { get; }
    public RelayCommand ExitCommand { get; }

    public MainViewModel(ILocalStore store, ISyncService sync)
    {
        _store = store;
        _sync = sync;

        LinksView = CollectionViewSource.GetDefaultView(Links);
        LinksView.Filter = FilterLinks;

        AddProjectCommand = new RelayCommand(AddProject);
        DeleteProjectCommand = new RelayCommand(DeleteSelectedProject, () => SelectedProject != null);

        AddTagCommand = new RelayCommand(AddTag);
        DeleteTagCommand = new RelayCommand(DeleteSelectedTag);

        AddLinkCommand = new RelayCommand(AddLink, () => SelectedProject != null);
        DeleteLinkCommand = new RelayCommand(DeleteSelectedLink, () => SelectedLink != null);
        SaveLinkCommand = new RelayCommand(SaveSelectedLink, () => SelectedLink != null);

        AssignTagCommand = new RelayCommand(AssignSelectedTagToSelectedLink, () => SelectedLink != null && TagToAdd != null);
        RemoveTagCommand = new RelayCommand<Tag>(RemoveTagFromSelectedLink);

        SyncCommand = new RelayCommand(async () => await SyncNowAsync());

        ExportJsonCommand = new RelayCommand(ExportJson);
        ExportCsvCommand = new RelayCommand(ExportCsv);
        ImportCommand = new RelayCommand(() => Status = "Import ist als nächster Schritt vorgesehen (noch nicht implementiert).");
        ClearSearchCommand = new RelayCommand(() => { SearchQuery = ""; Status = "Suche geleert."; });
        ResetFilterCommand = new RelayCommand(() => { FilterTag = null; Status = "Filter zurückgesetzt."; });
        AboutCommand = new RelayCommand(() =>
        {
            MessageBox.Show("SASD Links – Demo Client\nWPF / VS 2022\nOffline-First + Sync vorbereitet", "Über");
        });
        ExitCommand = new RelayCommand(() => Application.Current.Shutdown());

        _ = LoadAsync();
    }

    private async Task LoadAsync()
    {
        Status = "Loading...";
        _snapshot = await _store.LoadAsync();
        _pendingOps = await _store.LoadPendingOpsAsync();

        Projects.Clear();
        foreach (var p in _snapshot.Projects.OrderBy(p => p.Name))
            Projects.Add(p);

        Tags.Clear();
        foreach (var t in _snapshot.Tags.OrderBy(t => t.Name))
            Tags.Add(t);

        SelectedProject = Projects.FirstOrDefault();
        Status = "Ready";
    }

    private void RefreshLinks()
    {
        Links.Clear();

        if (SelectedProject == null)
        {
            LinksView.Refresh();
            return;
        }

        var list = _snapshot.Links
            .Where(l => l.ProjectId == SelectedProject.Id)
            .OrderByDescending(l => l.UpdatedAtUtc);

        foreach (var l in list)
            Links.Add(l);

        SelectedLink = Links.FirstOrDefault();

        DeleteProjectCommand.RaiseCanExecuteChanged();
        AddLinkCommand.RaiseCanExecuteChanged();
        LinksView.Refresh();
    }

    private bool FilterLinks(object obj)
    {
        if (obj is not LinkItem l) return false;

        if (FilterTag != null && !l.TagIds.Contains(FilterTag.Id))
            return false;

        if (!string.IsNullOrWhiteSpace(SearchQuery))
        {
            var q = SearchQuery.Trim().ToLowerInvariant();
            var hay = ((l.Title ?? "") + " " + (l.Description ?? "") + " " + (l.Url ?? "")).ToLowerInvariant();
            if (!hay.Contains(q))
                return false;
        }

        return true;
    }

    private async Task PersistAsync()
    {
        await _store.SaveAsync(_snapshot);
        await _store.SavePendingOpsAsync(_pendingOps);
    }

    private void AddPending(string kind, PendingOpType type, Guid id)
    {
        _pendingOps.Add(new PendingOp
        {
            EntityKind = kind,
            Type = type,
            EntityId = id
        });
    }

    private async void AddProject()
    {
        var name = (NewProjectName ?? "").Trim();
        if (name.Length == 0)
        {
            Status = "Projektname fehlt.";
            return;
        }

        if (_snapshot.Projects.Any(p => p.Name.Equals(name, StringComparison.OrdinalIgnoreCase)))
        {
            Status = "Projektname existiert bereits.";
            return;
        }

        var p = new Project { Name = name };
        _snapshot.Projects.Add(p);
        Projects.Add(p);
        SelectedProject = p;

        AddPending("project", PendingOpType.Upsert, p.Id);
        await PersistAsync();

        NewProjectName = "";
        Status = "Projekt angelegt.";
    }

    private async void DeleteSelectedProject()
    {
        if (SelectedProject == null) return;
        var pid = SelectedProject.Id;

        _snapshot.Projects.RemoveAll(p => p.Id == pid);
        _snapshot.Links.RemoveAll(l => l.ProjectId == pid);

        Projects.Remove(SelectedProject);
        SelectedProject = Projects.FirstOrDefault();

        AddPending("project", PendingOpType.Delete, pid);
        await PersistAsync();

        Status = "Projekt gelöscht.";
    }

    private async void AddTag()
    {
        var name = (NewTagName ?? "").Trim();
        if (name.Length == 0) { Status = "Tag-Name fehlt."; return; }

        if (_snapshot.Tags.Any(t => t.Name.Equals(name, StringComparison.OrdinalIgnoreCase)))
        {
            Status = "Tag existiert bereits.";
            return;
        }

        var t = new Tag { Name = name };
        _snapshot.Tags.Add(t);
        Tags.Add(t);

        AddPending("tag", PendingOpType.Upsert, t.Id);
        await PersistAsync();

        NewTagName = "";
        Status = "Tag angelegt.";
    }

    private async void DeleteSelectedTag()
    {
        if (FilterTag == null) { Status = "Zum Löschen Tag im Filter auswählen."; return; }

        var tid = FilterTag.Id;

        _snapshot.Tags.RemoveAll(t => t.Id == tid);
        foreach (var link in _snapshot.Links)
            link.TagIds.RemoveAll(x => x == tid);

        var tagObj = Tags.FirstOrDefault(t => t.Id == tid);
        if (tagObj != null) Tags.Remove(tagObj);

        FilterTag = null;
        AddPending("tag", PendingOpType.Delete, tid);
        await PersistAsync();

        Status = "Tag gelöscht.";
        RefreshLinks();
    }

    private async void AddLink()
    {
        if (SelectedProject == null) return;

        var raw = (EditUrl ?? "").Trim();
        var (ok, normalized, err) = UrlTools.Validate(raw);
        if (!ok) { Status = "Ungültige URL: " + err; return; }

        var canonical = UrlTools.Canonicalize(normalized);
        var hash = UrlTools.Sha256Hex(canonical);

        if (_snapshot.Links.Any(l => l.ProjectId == SelectedProject.Id && l.CanonicalHash == hash))
        {
            Status = "Warnung: URL existiert in diesem Projekt bereits.";
            return;
        }

        var link = new LinkItem
        {
            ProjectId = SelectedProject.Id,
            Url = normalized,
            CanonicalUrl = canonical,
            CanonicalHash = hash,
            Title = string.IsNullOrWhiteSpace(EditTitle) ? null : EditTitle.Trim(),
            Description = string.IsNullOrWhiteSpace(EditDescription) ? null : EditDescription.Trim()
        };

        _snapshot.Links.Add(link);
        Links.Insert(0, link);
        SelectedLink = link;

        AddPending("link", PendingOpType.Upsert, link.Id);
        await PersistAsync();

        Status = "Link gespeichert.";
        UpdateSelectedLinkTags();
    }

    private async void DeleteSelectedLink()
    {
        if (SelectedLink == null) return;

        var id = SelectedLink.Id;
        _snapshot.Links.RemoveAll(l => l.Id == id);

        Links.Remove(SelectedLink);
        SelectedLink = Links.FirstOrDefault();

        AddPending("link", PendingOpType.Delete, id);
        await PersistAsync();

        Status = "Link gelöscht.";
        UpdateSelectedLinkTags();
    }

    private async void SaveSelectedLink()
    {
        if (SelectedLink == null) return;

        var (ok, normalized, err) = UrlTools.Validate(EditUrl ?? "");
        if (!ok) { Status = "Ungültige URL: " + err; return; }

        var canonical = UrlTools.Canonicalize(normalized);
        var hash = UrlTools.Sha256Hex(canonical);

        if (_snapshot.Links.Any(l => l.ProjectId == SelectedLink.ProjectId && l.Id != SelectedLink.Id && l.CanonicalHash == hash))
        {
            Status = "Warnung: Änderung würde ein Duplikat erzeugen.";
            return;
        }

        SelectedLink.Url = normalized;
        SelectedLink.CanonicalUrl = canonical;
        SelectedLink.CanonicalHash = hash;
        SelectedLink.Title = string.IsNullOrWhiteSpace(EditTitle) ? null : EditTitle.Trim();
        SelectedLink.Description = string.IsNullOrWhiteSpace(EditDescription) ? null : EditDescription.Trim();
        SelectedLink.UpdatedAtUtc = DateTime.UtcNow;

        AddPending("link", PendingOpType.Upsert, SelectedLink.Id);
        await PersistAsync();

        LinksView.Refresh();
        Status = "Änderungen gespeichert.";
        UpdateSelectedLinkTags();
    }

    private void LoadSelectedLinkToEditor()
    {
        if (SelectedLink == null)
        {
            EditUrl = ""; EditTitle = ""; EditDescription = "";
            return;
        }

        EditUrl = SelectedLink.Url;
        EditTitle = SelectedLink.Title ?? "";
        EditDescription = SelectedLink.Description ?? "";
    }

    private void UpdateSelectedLinkTags()
    {
        SelectedLinkTags.Clear();
        if (SelectedLink == null) return;

        foreach (var tid in SelectedLink.TagIds)
        {
            var tag = _snapshot.Tags.FirstOrDefault(t => t.Id == tid);
            if (tag != null) SelectedLinkTags.Add(tag);
        }

        AssignTagCommand.RaiseCanExecuteChanged();
        DeleteLinkCommand.RaiseCanExecuteChanged();
        SaveLinkCommand.RaiseCanExecuteChanged();
    }

    private async void AssignSelectedTagToSelectedLink()
    {
        if (SelectedLink == null || TagToAdd == null) return;

        if (!SelectedLink.TagIds.Contains(TagToAdd.Id))
        {
            SelectedLink.TagIds.Add(TagToAdd.Id);
            SelectedLink.UpdatedAtUtc = DateTime.UtcNow;

            AddPending("link", PendingOpType.Upsert, SelectedLink.Id);
            await PersistAsync();

            Status = $"Tag '{TagToAdd.Name}' zugewiesen.";
            UpdateSelectedLinkTags();
            LinksView.Refresh();
        }
    }

    private async void RemoveTagFromSelectedLink(Tag? tag)
    {
        if (SelectedLink == null || tag == null) return;

        SelectedLink.TagIds.RemoveAll(x => x == tag.Id);
        SelectedLink.UpdatedAtUtc = DateTime.UtcNow;

        AddPending("link", PendingOpType.Upsert, SelectedLink.Id);
        await PersistAsync();

        Status = $"Tag '{tag.Name}' entfernt.";
        UpdateSelectedLinkTags();
        LinksView.Refresh();
    }

    private async Task SyncNowAsync()
    {
        Status = "Synchronisiere...";
        var res = await _sync.SyncAsync();
        Status = res.Ok ? res.Message : ("Fehler: " + res.Message);
        await LoadAsync();
    }

    private void ExportJson()
    {
        var dlg = new SaveFileDialog
        {
            Title = "Export (JSON)",
            Filter = "JSON Dateien (*.json)|*.json|Alle Dateien (*.*)|*.*",
            FileName = $"sasdlinks_export_{DateTime.UtcNow:yyyy-MM-dd}.json"
        };

        if (dlg.ShowDialog() != true) return;

        var json = Exporters.ToJson(_snapshot);
        File.WriteAllText(dlg.FileName, json, new UTF8Encoding(encoderShouldEmitUTF8Identifier: false));
        Status = "Export (JSON) geschrieben: " + dlg.FileName;
    }

    private void ExportCsv()
    {
        var dlg = new SaveFileDialog
        {
            Title = "Export (CSV)",
            Filter = "CSV Dateien (*.csv)|*.csv|Alle Dateien (*.*)|*.*",
            FileName = SelectedProject != null
                ? $"links_{SelectedProject.Name}_{DateTime.UtcNow:yyyy-MM-dd}.csv"
                : $"links_{DateTime.UtcNow:yyyy-MM-dd}.csv"
        };

        if (dlg.ShowDialog() != true) return;

        var csv = Exporters.LinksToCsv(_snapshot, SelectedProject?.Id);

        // Mit BOM, damit Excel UTF-8 sauber erkennt
        File.WriteAllText(dlg.FileName, csv, new UTF8Encoding(encoderShouldEmitUTF8Identifier: true));
        Status = "Export (CSV) geschrieben: " + dlg.FileName;
    }
}

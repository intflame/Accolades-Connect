import React, { useEffect, useState } from 'react';
import { supabase } from '../../lib/supabase';
import { useAuth } from '../../context/AuthContext';
import { Trash2, PlusCircle, Loader2, Database } from 'lucide-react';

interface Batch {
  id: number;
  name: string;
  student_count?: number;
}

export const ManageBatches: React.FC = () => {
  const { profile } = useAuth();
  const [batches, setBatches] = useState<Batch[]>([]);
  const [loading, setLoading] = useState(true);
  const [adding, setAdding] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [newBatchName, setNewBatchName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const fetchBatches = async () => {
    setLoading(true);
    try {
      const { data: batchesData, error: batchesError } = await supabase
        .from('batches')
        .select('*')
        .order('name', { ascending: false });

      if (batchesError) throw batchesError;

      const batchesList: Batch[] = batchesData || [];

      // Fetch student counts for each batch
      const updatedBatches = await Promise.all(
        batchesList.map(async (batch) => {
          const { count, error: countError } = await supabase
            .from('profiles')
            .select('*', { count: 'exact', head: true })
            .eq('role', 'student')
            .eq('batch_id', batch.id);

          if (countError) throw countError;

          return {
            ...batch,
            student_count: count || 0,
          };
        })
      );

      setBatches(updatedBatches);
    } catch (err: any) {
      console.error('Error fetching batches:', err);
      setError(err.message || 'Failed to load batches.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBatches();
  }, []);

  const handleAddBatch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newBatchName.trim()) return;

    setAdding(true);
    setError(null);
    setSuccess(null);

    try {
      // Check duplicate in local state first (or check db)
      const duplicate = batches.some((b) => b.name.toLowerCase() === newBatchName.trim().toLowerCase());
      if (duplicate) {
        throw new Error(`Batch '${newBatchName}' already exists.`);
      }

      const { data, error: insertError } = await supabase
        .from('batches')
        .insert({ name: newBatchName.trim() })
        .select()
        .single();

      if (insertError) throw insertError;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'batch_created',
        details: `Created batch: ${newBatchName.trim()}`,
      });

      setNewBatchName('');
      setSuccess(`Batch '${data.name}' added successfully.`);
      fetchBatches();
    } catch (err: any) {
      setError(err.message || 'Failed to create batch.');
    } finally {
      setAdding(false);
    }
  };

  const handleDeleteBatch = async (batchId: number, batchName: string) => {
    if (!window.confirm(`Are you sure you want to delete this batch? It will reset batch classifications for students enrolled in it.`)) {
      return;
    }

    setDeletingId(batchId);
    setError(null);
    setSuccess(null);

    try {
      const { error: deleteError } = await supabase
        .from('batches')
        .delete()
        .eq('id', batchId);

      if (deleteError) throw deleteError;

      // Log activity
      await supabase.from('activity_logs').insert({
        user_id: profile?.id,
        action: 'batch_deleted',
        details: `Deleted batch: ${batchName}`,
      });

      setSuccess(`Batch '${batchName}' deleted successfully.`);
      fetchBatches();
    } catch (err: any) {
      setError(err.message || 'Failed to delete batch.');
    } finally {
      setDeletingId(null);
    }
  };

  if (loading) {
    return (
      <div className="container main-content" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
        <p>Loading academic batches...</p>
      </div>
    );
  }

  return (
    <div className="container main-content">
      <div style={{ marginBottom: '2rem' }}>
        <h2>Manage Batches</h2>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem' }}>
          Add or remove academic batches cohorts used for student profile categorization.
        </p>
      </div>

      {error && (
        <div className="alert alert-danger show-alert-anim">
          <div className="alert-content">{error}</div>
        </div>
      )}

      {success && (
        <div className="alert alert-success show-alert-anim">
          <div className="alert-content">{success}</div>
        </div>
      )}

      <div className="dashboard-panel">
        {/* Left Side: Active Batches List */}
        <div>
          <div className="card">
            <div className="card-header" style={{ marginBottom: '1.5rem' }}>
              <h3>Active Batches List</h3>
            </div>

            {batches.length === 0 ? (
              <p style={{ color: 'var(--text-muted)', textAlign: 'center', padding: '2rem 0' }}>
                No batches defined yet. Use the panel on the right to add one.
              </p>
            ) : (
              <div className="table-responsive">
                <table className="table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Batch Name</th>
                      <th>Enrolled Students</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {batches.map((batch) => (
                      <tr key={batch.id}>
                        <td>{batch.id}</td>
                        <td style={{ fontWeight: 600 }}>{batch.name}</td>
                        <td>{batch.student_count} Student(s)</td>
                        <td>
                          <button
                            onClick={() => handleDeleteBatch(batch.id, batch.name)}
                            className="btn btn-danger btn-sm"
                            disabled={deletingId === batch.id || (batch.student_count !== undefined && batch.student_count > 0)}
                            title={batch.student_count !== undefined && batch.student_count > 0 ? "Cannot delete batch with enrolled students." : "Delete Batch"}
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}
                          >
                            {deletingId === batch.id ? (
                              <Loader2 className="animate-spin" size={14} />
                            ) : (
                              <Trash2 size={14} />
                            )}
                            Delete
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>

        {/* Right Side: Add Batch Widget */}
        <div>
          <div className="card">
            <div className="card-header" style={{ marginBottom: '1.5rem' }}>
              <h3>Add New Academic Batch</h3>
            </div>

            <form onSubmit={handleAddBatch}>
              <div className="form-group">
                <label className="form-label" htmlFor="name">Batch Cohort Name</label>
                <input
                  type="text"
                  id="name"
                  className="form-control"
                  placeholder="e.g. 2023-2027"
                  value={newBatchName}
                  onChange={(e) => setNewBatchName(e.target.value)}
                  required
                />
                <small style={{ color: 'var(--text-muted)', fontSize: '0.75rem', display: 'block', marginTop: '0.25rem' }}>
                  Usually formatted as START_YEAR-END_YEAR (e.g. 2024-2028).
                </small>
              </div>

              <button
                type="submit"
                className="btn btn-primary"
                style={{ width: '100%', marginTop: '1rem', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '6px' }}
                disabled={adding}
              >
                {adding ? (
                  <Loader2 className="animate-spin" size={16} />
                ) : (
                  <PlusCircle size={16} />
                )}
                Create Batch
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};

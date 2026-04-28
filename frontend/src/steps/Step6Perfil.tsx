import { useEffect } from 'react';

import { Button } from '../components/Button';
import { ErrorMessage } from '../components/ErrorMessage';
import { SelectField, TextField, TextareaField } from '../components/Field';
import { StepFrame } from '../components/StepFrame';
import { useProfile } from '../api/profile';
import { useBookingStore } from '../store/bookingStore';
import { PROVINCIAS, isProvincia } from '../data/provincias';
import { isValidProfile, validateProfile } from '../hooks/profileValidation';

import styles from './Step6Perfil.module.css';

export function Step6Perfil(): JSX.Element {
    const profile = useBookingStore((s) => s.profile);
    const patchProfile = useBookingStore((s) => s.patchProfile);
    const setProfile = useBookingStore((s) => s.setProfile);
    const objetoReserva = useBookingStore((s) => s.objetoReserva);
    const setObjeto = useBookingStore((s) => s.setObjeto);
    const goBack = useBookingStore((s) => s.goBack);
    const setStep = useBookingStore((s) => s.setStep);

    const loggedIn = window.ReservasAldealab?.isLoggedIn === true;
    const { data } = useProfile(loggedIn);

    useEffect(() => {
        if (data?.profile != null && profile.email === '') {
            setProfile({ ...data.profile });
        }
    }, [data, profile.email, setProfile]);

    const errors = validateProfile(profile);
    const canProceed = isValidProfile(profile) && objetoReserva.trim() !== '';

    return (
        <StepFrame
            title="Tus datos"
            subtitle="Introduce tus datos personales. Revisa que estén correctos."
            actions={
                <>
                    <Button variant="ghost" onClick={goBack}>
                        ← Atrás
                    </Button>
                    <Button onClick={() => setStep(7)} disabled={!canProceed} data-step-advance>
                        Siguiente →
                    </Button>
                </>
            }
        >
            {!canProceed && Object.keys(errors).length > 0 && (
                <ErrorMessage message="Completa los campos marcados antes de continuar." />
            )}
            <div className={styles.grid}>
                <TextField
                    label="NIF"
                    value={profile.nif}
                    onChange={(e) => patchProfile({ nif: e.target.value })}
                    required
                    error={errors.nif ?? null}
                />
                <TextField
                    label="Nombre"
                    value={profile.nombre}
                    onChange={(e) => patchProfile({ nombre: e.target.value })}
                    required
                    error={errors.nombre ?? null}
                />
                <TextField
                    label="Primer apellido"
                    value={profile.primer_apellido}
                    onChange={(e) => patchProfile({ primer_apellido: e.target.value })}
                    required
                    error={errors.primer_apellido ?? null}
                />
                <TextField
                    label="Segundo apellido"
                    value={profile.segundo_apellido ?? ''}
                    onChange={(e) =>
                        patchProfile({
                            segundo_apellido: e.target.value === '' ? null : e.target.value,
                        })
                    }
                />
                <TextField
                    label="Email"
                    type="email"
                    value={profile.email}
                    onChange={(e) => patchProfile({ email: e.target.value })}
                    required
                    error={errors.email ?? null}
                />
                <TextField
                    label="Móvil"
                    value={profile.movil}
                    onChange={(e) => patchProfile({ movil: e.target.value })}
                    required
                    error={errors.movil ?? null}
                />
                <TextField
                    label="Teléfono fijo"
                    value={profile.telefono_fijo ?? ''}
                    onChange={(e) =>
                        patchProfile({
                            telefono_fijo: e.target.value === '' ? null : e.target.value,
                        })
                    }
                />
                <TextField
                    label="Empresa"
                    hint="Opcional"
                    value={profile.empresa ?? ''}
                    onChange={(e) =>
                        patchProfile({ empresa: e.target.value === '' ? null : e.target.value })
                    }
                />
                <TextField
                    label="Calle / Plaza / Avenida"
                    value={profile.via}
                    onChange={(e) => patchProfile({ via: e.target.value })}
                    required
                    error={errors.via ?? null}
                />
                <TextField
                    label="Número"
                    value={profile.numero}
                    onChange={(e) => patchProfile({ numero: e.target.value })}
                    required
                    error={errors.numero ?? null}
                />
                <TextField
                    label="Letra"
                    value={profile.letra ?? ''}
                    onChange={(e) =>
                        patchProfile({ letra: e.target.value === '' ? null : e.target.value })
                    }
                />
                <TextField
                    label="Escalera"
                    value={profile.escalera ?? ''}
                    onChange={(e) =>
                        patchProfile({ escalera: e.target.value === '' ? null : e.target.value })
                    }
                />
                <TextField
                    label="Piso"
                    value={profile.piso ?? ''}
                    onChange={(e) =>
                        patchProfile({ piso: e.target.value === '' ? null : e.target.value })
                    }
                />
                <TextField
                    label="Puerta"
                    value={profile.puerta ?? ''}
                    onChange={(e) =>
                        patchProfile({ puerta: e.target.value === '' ? null : e.target.value })
                    }
                />
                <TextField
                    label="Municipio"
                    value={profile.municipio}
                    onChange={(e) => patchProfile({ municipio: e.target.value })}
                    required
                    error={errors.municipio ?? null}
                />
                <SelectField
                    label="Provincia"
                    value={profile.provincia}
                    onChange={(e) => patchProfile({ provincia: e.target.value })}
                    required
                    error={errors.provincia ?? null}
                    hint={
                        profile.provincia !== '' && !isProvincia(profile.provincia)
                            ? `El valor anterior ("${profile.provincia}") no está en la lista. Selecciona una.`
                            : ''
                    }
                    options={[
                        { value: '', label: '— Selecciona —' },
                        ...PROVINCIAS.map((p) => ({ value: p, label: p })),
                    ]}
                />
                <TextField
                    label="Código postal"
                    value={profile.codigo_postal}
                    onChange={(e) => patchProfile({ codigo_postal: e.target.value })}
                    required
                    error={errors.codigo_postal ?? null}
                />
            </div>
            <TextareaField
                label="Objeto de la reserva"
                value={objetoReserva}
                onChange={(e) => setObjeto(e.target.value)}
                rows={3}
                required
                hint="Describe brevemente para qué vas a usar el espacio."
            />
        </StepFrame>
    );
}
